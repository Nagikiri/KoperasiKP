<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpananService
{
    /**
     * Rekap simpanan per jenis (BR-14): total setor, total tarik, dan saldo.
     * Ketiga jenis selalu muncul walau belum ada transaksi. Mencakup anggota
     * aktif maupun nonaktif.
     *
     * @return Collection<int, array{jenis_simpanan_id: int, nama_jenis: string, total_setor: float, total_tarik: float, saldo: float}>
     */
    public function rekapPerJenis(): Collection
    {
        $agregat = TransaksiSimpanan::query()
            ->selectRaw("jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->groupBy('jenis_simpanan_id')
            ->get()
            ->keyBy('jenis_simpanan_id');

        return JenisSimpanan::query()
            ->orderBy('id')
            ->get()
            ->map(function (JenisSimpanan $jenis) use ($agregat) {
                $baris = $agregat->get($jenis->id);

                $totalSetor = round((float) ($baris->total_setor ?? 0), 2);
                $totalTarik = round((float) ($baris->total_tarik ?? 0), 2);

                return [
                    'jenis_simpanan_id' => $jenis->id,
                    'nama_jenis' => $jenis->nama_jenis,
                    'total_setor' => $totalSetor,
                    'total_tarik' => $totalTarik,
                    'saldo' => round($totalSetor - $totalTarik, 2),
                ];
            })
            ->values();
    }

    /**
     * Catat setoran (FR-11). Berlaku untuk ketiga jenis simpanan (BR-06).
     */
    public function setor(int $anggotaId, int $jenisSimpananId, int $jumlah, string $tanggal, int $staffId, ?string $kodeReferensi = null): TransaksiSimpanan
    {
        return $this->catat(TransaksiSimpanan::SETOR, $anggotaId, $jenisSimpananId, $jumlah, $tanggal, $staffId, $kodeReferensi);
    }

    /**
     * Catat penarikan (FR-12). Jenis selalu Simpanan Sukarela, ditentukan server (BR-06).
     */
    public function tarik(int $anggotaId, int $jumlah, string $tanggal, int $staffId, ?string $kodeReferensi = null): TransaksiSimpanan
    {
        return $this->catat(TransaksiSimpanan::TARIK, $anggotaId, JenisSimpanan::sukarela()->id, $jumlah, $tanggal, $staffId, $kodeReferensi);
    }

    /**
     * Alur simpan transaksi (BR-13): transaksi DB -> kunci anggota -> cek kiriman
     * ganda (BR-19) -> cek status -> cek tanggal -> hitung saldo -> cek saldo
     * (tarik) -> insert.
     *
     * Kiriman ganda dengan kode_referensi yang sama tidak di-insert lagi: yang
     * dikembalikan adalah transaksi yang sudah ada, dengan
     * `wasRecentlyCreated === false` sebagai penanda duplikat.
     */
    private function catat(string $jenisTransaksi, int $anggotaId, int $jenisSimpananId, int $jumlah, string $tanggal, int $staffId, ?string $kodeReferensi): TransaksiSimpanan
    {
        try {
            return DB::transaction(fn () => $this->catatDalamTransaksi(
                $jenisTransaksi, $anggotaId, $jenisSimpananId, $jumlah, $tanggal, $staffId, $kodeReferensi
            ));
        } catch (UniqueConstraintViolationException $e) {
            // Pengaman terakhir: dua kiriman lolos cek bersamaan, unique index menolak yang kedua.
            $sudahAda = $kodeReferensi === null ? null : $this->cariByKodeReferensi($kodeReferensi);

            if ($sudahAda === null) {
                throw $e;
            }

            return $sudahAda;
        }
    }

    private function catatDalamTransaksi(string $jenisTransaksi, int $anggotaId, int $jenisSimpananId, int $jumlah, string $tanggal, int $staffId, ?string $kodeReferensi): TransaksiSimpanan
    {
        $anggota = Anggota::whereKey($anggotaId)->lockForUpdate()->firstOrFail();

        if ($kodeReferensi !== null && ($sudahAda = $this->cariByKodeReferensi($kodeReferensi)) !== null) {
            return $sudahAda;
        }

        return $this->simpanBaru($anggota, $jenisTransaksi, $jenisSimpananId, $jumlah, $tanggal, $staffId, $kodeReferensi);
    }

    private function cariByKodeReferensi(string $kodeReferensi): ?TransaksiSimpanan
    {
        return TransaksiSimpanan::where('kode_referensi', $kodeReferensi)->first();
    }

    private function simpanBaru(Anggota $anggota, string $jenisTransaksi, int $jenisSimpananId, int $jumlah, string $tanggal, int $staffId, ?string $kodeReferensi): TransaksiSimpanan
    {
        if (! $anggota->isAktif()) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota nonaktif tidak dapat melakukan transaksi.',
            ]);
        }

        $this->validasiTanggal($anggota, $jenisSimpananId, $tanggal); // BR-10

        $saldo = $anggota->saldoPerJenis($jenisSimpananId);

        if ($jenisTransaksi === TransaksiSimpanan::TARIK && $jumlah > $saldo) {
            throw ValidationException::withMessages([
                'jumlah' => 'Penarikan gagal. Saldo Simpanan Sukarela anggota ini hanya ' . Format::rupiah($saldo) . '.',
            ]);
        }

        $saldoAkhir = $jenisTransaksi === TransaksiSimpanan::SETOR ? $saldo + $jumlah : $saldo - $jumlah;

        return TransaksiSimpanan::create([
            'kode_referensi' => $kodeReferensi,
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenisSimpananId,
            'staff_id' => $staffId,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => $saldoAkhir,
        ]);
    }

    /**
     * BR-10: tanggal transaksi >= tanggal bergabung dan >= tanggal transaksi
     * terakhir anggota untuk jenis yang sama (tanggal sama boleh).
     */
    private function validasiTanggal(Anggota $anggota, int $jenisSimpananId, string $tanggal): void
    {
        $tanggalTransaksi = Carbon::parse($tanggal)->startOfDay();
        $tanggalBergabung = Carbon::parse($anggota->tanggal_bergabung)->startOfDay();

        if ($tanggalTransaksi->lt($tanggalBergabung)) {
            throw ValidationException::withMessages([
                'tanggal_transaksi' => 'Tanggal transaksi tidak boleh sebelum tanggal bergabung anggota (' . Format::tanggal($tanggalBergabung) . ').',
            ]);
        }

        $terakhir = TransaksiSimpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->max('tanggal_transaksi');

        if ($terakhir === null) {
            return;
        }

        $tanggalTerakhir = Carbon::parse($terakhir)->startOfDay();

        if ($tanggalTransaksi->lt($tanggalTerakhir)) {
            $namaJenis = JenisSimpanan::whereKey($jenisSimpananId)->value('nama_jenis');

            throw ValidationException::withMessages([
                'tanggal_transaksi' => "Tanggal transaksi tidak boleh lebih awal dari transaksi terakhir {$namaJenis} anggota ini (" . Format::tanggal($tanggalTerakhir) . ').',
            ]);
        }
    }
}
