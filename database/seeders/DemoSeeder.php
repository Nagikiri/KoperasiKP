<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\NomorAnggotaGenerator;
use App\Services\SimpananService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo untuk dashboard dan laporan (F2b A.3). Bisa dihapus nanti.
 *
 * TIDAK dipanggil dari DatabaseSeeder. Jalankan manual di database kosong:
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * Hasilnya deterministik untuk tanggal "hari ini" yang sama (seed 2026). Rentang
 * data dihitung dari now(), jadi isinya berbeda kalau dijalankan di hari lain.
 */
class DemoSeeder extends Seeder
{
    public static int $jumlahAnggota = 24;

    public static int $jumlahTahun = 5;

    private const JUMLAH_NONAKTIF = 3;

    private const POKOK = 100000;

    private const WAJIB = 50000;

    private const CATATAN_SETOR_SUKARELA = ['Tabungan hari raya', 'Tabungan pendidikan anak', 'Sisa hasil panen', 'Tabungan akhir tahun'];

    private const CATATAN_TARIK = ['Keperluan sekolah anak', 'Biaya berobat', 'Kebutuhan hari raya', 'Perbaikan rumah'];

    public function run(NomorAnggotaGenerator $generator, SimpananService $simpananService): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan di produksi.');

            return;
        }

        if (Anggota::query()->exists()) {
            $this->command?->error('DemoSeeder hanya untuk database kosong. Jalankan migrate:fresh --seed dulu.');

            return;
        }

        $faker = fake('id_ID');
        $faker->seed(2026); // juga mengatur mt_srand, jadi mt_rand() di bawah ikut deterministik

        $staffId = User::where('username', 'admin')->firstOrFail()->id;
        $jenisId = JenisSimpanan::pluck('id', 'nama_jenis');
        $hariIni = now()->startOfDay();

        [$jumlahAnggota, $jumlahTransaksi] = DB::transaction(function () use ($faker, $generator, $simpananService, $staffId, $jenisId, $hariIni) {
            $rencana = $this->rencanaAnggota($hariIni);

            // Anggota dibuat lewat NomorAnggotaGenerator di dalam DB::transaction, sama seperti
            // aplikasi. Karena itu nomor anggota memakai tahun saat seeder dijalankan
            // (KSP-{tahun ini}-xxxx, BR-01), walaupun tanggal bergabungnya lebih lama.
            $nikDipakai = [];
            foreach ($rencana as &$item) {
                do {
                    $nik = '6471'.str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT).str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                } while (isset($nikDipakai[$nik]));
                $nikDipakai[$nik] = true;

                $item['anggota'] = DB::transaction(fn () => Anggota::create([
                    'no_anggota' => $generator->generate(),
                    'nama' => $faker->name(),
                    'nik' => $nik,
                    'alamat' => $faker->address(),
                    'no_telepon' => '08'.str_pad((string) mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT),
                    'tanggal_bergabung' => $item['bergabung']->toDateString(),
                    'status' => Anggota::STATUS_AKTIF,
                ]));
            }
            unset($item);

            // Transaksi semua anggota diurutkan kronologis secara global, supaya id ikut urutan waktu.
            $semua = [];
            foreach ($rencana as $indeks => $item) {
                foreach ($this->rencanaTransaksi($item, $hariIni) as $urutan => $transaksi) {
                    $semua[] = $transaksi + ['anggota_id' => $item['anggota']->id, 'kunci' => sprintf('%s-%04d-%04d', $transaksi['tanggal'], $indeks, $urutan)];
                }
            }
            usort($semua, fn (array $a, array $b) => strcmp($a['kunci'], $b['kunci']));

            // Lewat SimpananService, bukan insert langsung, supaya semua BR berlaku dan saldo_akhir benar.
            foreach ($semua as $t) {
                if ($t['jenis'] === 'tarik') {
                    $simpananService->tarik($t['anggota_id'], $t['jumlah'], $t['tanggal'], $staffId, null, $t['catatan']);
                } else {
                    $simpananService->setor($t['anggota_id'], (int) $jenisId[$t['jenis']], $t['jumlah'], $t['tanggal'], $staffId, null, $t['catatan']);
                }
            }

            // Anggota nonaktif ditetapkan di akhir proses.
            foreach ($rencana as $item) {
                if ($item['nonaktif']) {
                    $item['anggota']->update(['status' => Anggota::STATUS_NONAKTIF]);
                }
            }

            return [count($rencana), count($semua)];
        });

        $this->command?->info("DemoSeeder: {$jumlahAnggota} anggota dan {$jumlahTransaksi} transaksi dibuat.");
    }

    /**
     * Tanggal bergabung tersebar dari $jumlahTahun tahun lalu sampai bulan ini
     * (beberapa bergabung bulan ini), urut lama -> baru. Juga menentukan siapa
     * yang nanti dinonaktifkan dan siapa yang melewatkan setoran wajib bulan ini.
     *
     * @return list<array{bergabung: Carbon, nonaktif: bool, lewati_wajib_bulan_ini: bool}>
     */
    private function rencanaAnggota(Carbon $hariIni): array
    {
        $jumlah = static::$jumlahAnggota;
        $jumlahBaru = max(1, intdiv($jumlah, 8));
        $awalBulanIni = $hariIni->copy()->startOfMonth();
        $palingAwal = $hariIni->copy()->subYears(static::$jumlahTahun);
        $rentangHari = max(0, (int) $palingAwal->diffInDays($awalBulanIni) - 1);

        $tanggal = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $tanggal[] = $i < $jumlahBaru
                ? $awalBulanIni->copy()->addDays(mt_rand(0, $hariIni->day - 1))
                : $palingAwal->copy()->addDays(mt_rand(0, $rentangHari));
        }
        usort($tanggal, fn (Carbon $a, Carbon $b) => $a <=> $b);

        // Anggota yang bergabung bulan ini tidak dinonaktifkan; pilih yang lain secara merata.
        $kandidat = range(0, max(0, $jumlah - $jumlahBaru - 1));
        $nonaktif = [];
        $jumlahNonaktif = min(self::JUMLAH_NONAKTIF, count($kandidat));
        for ($i = 0; $i < $jumlahNonaktif; $i++) {
            $nonaktif[$kandidat[intdiv(($i * 2 + 1) * count($kandidat), $jumlahNonaktif * 2)]] = true;
        }

        $rencana = [];
        $aktifLama = 0;
        foreach ($tanggal as $indeks => $bergabung) {
            $isNonaktif = isset($nonaktif[$indeks]);
            $bergabungBulanIni = $bergabung->gte($awalBulanIni);
            $lewati = false;

            if (! $isNonaktif && ! $bergabungBulanIni) {
                // Sekitar separuh anggota aktif melewatkan setoran wajib bulan ini (selang-seling).
                $lewati = $aktifLama % 2 === 0;
                $aktifLama++;
            }

            $rencana[] = ['bergabung' => $bergabung, 'nonaktif' => $isNonaktif, 'lewati_wajib_bulan_ini' => $lewati];
        }

        return $rencana;
    }

    /**
     * Rencana transaksi satu anggota, kronologis per jenis (BR-10), semua tanggal
     * di antara tanggal bergabung dan hari ini.
     *
     * @param  array{bergabung: Carbon, nonaktif: bool, lewati_wajib_bulan_ini: bool}  $item
     * @return list<array{tanggal: string, jenis: string, jumlah: int, catatan: ?string}>
     */
    private function rencanaTransaksi(array $item, Carbon $hariIni): array
    {
        $bergabung = $item['bergabung'];
        $hasil = [[
            'tanggal' => $bergabung->toDateString(),
            'jenis' => JenisSimpanan::POKOK,
            'jumlah' => self::POKOK,
            'catatan' => 'Simpanan pokok anggota baru',
        ]];

        $saldoSukarela = 0;
        $bulan = $bergabung->copy()->startOfMonth();
        $bulanIni = $hariIni->copy()->startOfMonth();

        while ($bulan->lte($bulanIni)) {
            $awal = $bulan->copy()->max($bergabung);
            $akhir = $bulan->copy()->endOfMonth()->startOfDay()->min($hariIni);
            $isBulanBergabung = $bulan->isSameMonth($bergabung);
            $isBulanIni = $bulan->equalTo($bulanIni);

            // Simpanan Wajib.
            $tanggalWajib = null;
            if ($isBulanBergabung) {
                $tanggalWajib = $this->tanggalAcak($awal, $akhir);
            } elseif ($isBulanIni) {
                if (! $item['lewati_wajib_bulan_ini']) {
                    $tanggalWajib = $this->tanggalAcak($bulan, $bulan->copy()->addDays(min(10, $hariIni->day) - 1));
                }
            } elseif (mt_rand(1, 10) > 1) { // kira-kira 10% bulan dilewati
                $tanggalWajib = $bulan->copy()->addDays(mt_rand(0, 9));
            }

            if ($tanggalWajib !== null) {
                $hasil[] = [
                    'tanggal' => $tanggalWajib->toDateString(),
                    'jenis' => JenisSimpanan::WAJIB,
                    'jumlah' => self::WAJIB,
                    'catatan' => mt_rand(0, 1) === 1 ? 'Setoran wajib '.$bulan->translatedFormat('F Y') : null,
                ];
            }

            // Simpanan Sukarela: setor 0-3 kali, tarik 0-1 kali; diurutkan per tanggal.
            $sukarela = [];
            for ($i = mt_rand(0, 3); $i > 0; $i--) {
                $sukarela[] = ['tanggal' => $this->tanggalAcak($awal, $akhir), 'jenis' => JenisSimpanan::SUKARELA];
            }
            if (mt_rand(0, 1) === 1) {
                $sukarela[] = ['tanggal' => $this->tanggalAcak($awal, $akhir), 'jenis' => 'tarik'];
            }
            usort($sukarela, fn (array $a, array $b) => [$a['tanggal'], $a['jenis'] === 'tarik'] <=> [$b['tanggal'], $b['jenis'] === 'tarik']);

            foreach ($sukarela as $s) {
                if ($s['jenis'] === 'tarik') {
                    $maks = intdiv((int) floor($saldoSukarela * 0.6), 5000);
                    if ($maks < 1) {
                        continue;
                    }
                    $jumlah = mt_rand(1, $maks) * 5000;
                    $saldoSukarela -= $jumlah;
                    $catatan = mt_rand(0, 2) === 0 ? self::CATATAN_TARIK[mt_rand(0, count(self::CATATAN_TARIK) - 1)] : null;
                } else {
                    $jumlah = mt_rand(5, 200) * 5000; // Rp 25.000 - 1.000.000
                    $saldoSukarela += $jumlah;
                    $catatan = mt_rand(0, 3) === 0 ? self::CATATAN_SETOR_SUKARELA[mt_rand(0, count(self::CATATAN_SETOR_SUKARELA) - 1)] : null;
                }

                $hasil[] = ['tanggal' => $s['tanggal']->toDateString(), 'jenis' => $s['jenis'], 'jumlah' => $jumlah, 'catatan' => $catatan];
            }

            $bulan->addMonthNoOverflow();
        }

        return $hasil;
    }

    private function tanggalAcak(Carbon $awal, Carbon $akhir): Carbon
    {
        return $awal->copy()->addDays(mt_rand(0, max(0, (int) $awal->diffInDays($akhir))));
    }
}
