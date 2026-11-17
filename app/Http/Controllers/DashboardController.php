<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\GrafikService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard ringkasan (FR-20, diperluas revisi R3). Semua angka dari query
     * agregasi; tidak ada N+1. `grafikArus` ditambahkan di F2b (A.2). Filter
     * transaksi terbaru (`filterTransaksi`, `jenisSimpanan`) ditambahkan di F2c (D.2).
     */
    public function index(Request $request, SimpananService $simpananService, GrafikService $grafikService): View
    {
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();
        $filterTransaksi = $this->filterTransaksi($request, $jenisSimpanan);

        $hariIni = now()->startOfDay();
        $awalBulan = now()->startOfMonth();
        $akhirBulan = now()->endOfMonth();

        $rekapSaldo = $simpananService->rekapPerJenis();

        $wajibId = $jenisSimpanan->firstWhere('nama_jenis', JenisSimpanan::WAJIB)?->id;
        $wajibId = $wajibId === null ? null : (int) $wajibId;

        // Bulan ini selalu dihitung: dipakai kalimat status (F2d I.5.1) dan ringkasan sebaran.
        [$belumSetorWajib, $jumlahBelumSetorWajib] = $this->belumSetorWajib($wajibId, $awalBulan, $akhirBulan);

        $ringkasanAnggota = $this->ringkasanAnggota($awalBulan, $akhirBulan);

        return view('dashboard', [
            'ringkasanAnggota' => $ringkasanAnggota,
            'ringkasanSebaran' => $this->ringkasanSebaran($jenisSimpanan, $wajibId, $awalBulan, $akhirBulan, $ringkasanAnggota['aktif'], $jumlahBelumSetorWajib),
            'rekapSaldo' => $rekapSaldo,
            'totalSaldo' => round((float) $rekapSaldo->sum('saldo'), 2),
            'aktivitasHariIni' => $this->aktivitas($hariIni, $hariIni),
            'aktivitasBulanIni' => $this->aktivitas($awalBulan, $akhirBulan),
            'trenBulanan' => $this->trenBulanan(),
            'belumSetorWajib' => $belumSetorWajib,
            'jumlahBelumSetorWajib' => $jumlahBelumSetorWajib,
            // F2d I.5.2: anggota yang belum setor wajib bulan ini DAN bulan lalu.
            'jumlahBelumSetorWajibDuaBulan' => $jumlahBelumSetorWajib > 0 ? $this->jumlahBelumSetorWajibDuaBulan($wajibId) : 0,
            // F2d I.4: kartu "Belum setor wajib" mengikuti pilihan dropdown (`wajib`).
            'wajib' => $this->wajib($request, $wajibId, $belumSetorWajib, $jumlahBelumSetorWajib),
            'transaksiTerbaru' => TransaksiSimpanan::with(['anggota', 'jenisSimpanan', 'staff'])
                ->filter($filterTransaksi)
                ->orderByDesc('tanggal_transaksi')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            // F2d E.6: jumlah transaksi yang cocok dengan filter (untuk kaki "Lihat semua {n} transaksi").
            'jumlahTransaksiCocok' => TransaksiSimpanan::query()->filter($filterTransaksi)->count(),
            'grafikArus' => $grafikService->arusSimpanan(),
            'filterTransaksi' => $filterTransaksi,
            'jenisSimpanan' => $jenisSimpanan,
        ]);
    }

    /**
     * Parameter filter transaksi terbaru (F2c D.2). Key = nama query parameter,
     * nilai string|null; nilai tidak valid menjadi null (diabaikan diam-diam).
     *
     * @param  Collection<int, JenisSimpanan>  $jenisSimpanan
     * @return array{cari: ?string, jenis_transaksi: ?string, jenis_simpanan_id: ?string, rentang: ?string}
     */
    private function filterTransaksi(Request $request, Collection $jenisSimpanan): array
    {
        $teks = function (string $kunci) use ($request): ?string {
            $nilai = $request->query($kunci);
            $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';

            return $nilai === '' ? null : $nilai;
        };

        $jenisTransaksi = $teks('jenis_transaksi');
        $jenisSimpananId = $teks('jenis_simpanan_id');
        $rentang = $teks('rentang');

        return [
            'cari' => $teks('cari'),
            'jenis_transaksi' => in_array($jenisTransaksi, [TransaksiSimpanan::SETOR, TransaksiSimpanan::TARIK], true) ? $jenisTransaksi : null,
            'jenis_simpanan_id' => $jenisSimpanan->contains(fn ($jenis) => (string) $jenis->id === $jenisSimpananId) ? $jenisSimpananId : null,
            'rentang' => in_array($rentang, TransaksiSimpanan::RENTANG, true) ? $rentang : null,
        ];
    }

    /**
     * @return array{aktif: int, nonaktif: int, baru_bulan_ini: int}
     */
    private function ringkasanAnggota(Carbon $awalBulan, Carbon $akhirBulan): array
    {
        $perStatus = Anggota::query()
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'aktif' => (int) ($perStatus[Anggota::STATUS_AKTIF] ?? 0),
            'nonaktif' => (int) ($perStatus[Anggota::STATUS_NONAKTIF] ?? 0),
            'baru_bulan_ini' => Anggota::query()
                ->whereDate('tanggal_bergabung', '>=', $awalBulan->toDateString())
                ->whereDate('tanggal_bergabung', '<=', $akhirBulan->toDateString())
                ->count(),
        ];
    }

    /**
     * Ringkasan di bawah donat "Sebaran simpanan" (F2d I.3). Satu query agregasi saldo per
     * status anggota × jenis, ditambah satu query setoran Wajib bulan ini. Jenis dikenali
     * lewat konstanta JenisSimpanan, bukan teks di Blade.
     *
     * bisa_ditarik + tertahan + milik_nonaktif = total saldo (rekapPerJenis).
     *
     * @param  Collection<int, JenisSimpanan>  $jenisSimpanan
     * @return array{bisa_ditarik: float, tertahan: float, milik_nonaktif: float, rata_rata_aktif: ?float, wajib_masuk_bulan_ini: float, sudah_setor_wajib: int, anggota_aktif: int}
     */
    private function ringkasanSebaran(Collection $jenisSimpanan, ?int $wajibId, Carbon $awalBulan, Carbon $akhirBulan, int $anggotaAktif, int $jumlahBelumSetorWajib): array
    {
        $idJenis = $jenisSimpanan->mapWithKeys(fn ($jenis) => [$jenis->nama_jenis => (int) $jenis->id]);
        $sukarelaId = $idJenis[JenisSimpanan::SUKARELA] ?? null;
        $tertahanId = array_values(array_filter([$idJenis[JenisSimpanan::POKOK] ?? null, $idJenis[JenisSimpanan::WAJIB] ?? null]));

        $bisaDitarik = 0.0;
        $tertahan = 0.0;
        $milikNonaktif = 0.0;

        TransaksiSimpanan::query()
            ->join('anggota', 'anggota.id', '=', 'transaksi_simpanan.anggota_id')
            ->selectRaw("anggota.status, transaksi_simpanan.jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN transaksi_simpanan.jenis_transaksi = 'setor' THEN transaksi_simpanan.jumlah ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN transaksi_simpanan.jenis_transaksi = 'tarik' THEN transaksi_simpanan.jumlah ELSE 0 END), 0) AS saldo")
            ->groupBy('anggota.status', 'transaksi_simpanan.jenis_simpanan_id')
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$bisaDitarik, &$tertahan, &$milikNonaktif, $sukarelaId, $tertahanId) {
                $saldo = (float) $baris->saldo;

                if ($baris->status !== Anggota::STATUS_AKTIF) {
                    $milikNonaktif += $saldo;
                } elseif ((int) $baris->jenis_simpanan_id === $sukarelaId) {
                    $bisaDitarik += $saldo;
                } elseif (in_array((int) $baris->jenis_simpanan_id, $tertahanId, true)) {
                    $tertahan += $saldo;
                }
            });

        $wajibMasuk = (float) TransaksiSimpanan::query()
            ->where('jenis_simpanan_id', $wajibId)
            ->where('jenis_transaksi', TransaksiSimpanan::SETOR)
            ->whereDate('tanggal_transaksi', '>=', $awalBulan->toDateString())
            ->whereDate('tanggal_transaksi', '<=', $akhirBulan->toDateString())
            ->sum('jumlah');

        return [
            'bisa_ditarik' => round($bisaDitarik, 2),
            'tertahan' => round($tertahan, 2),
            'milik_nonaktif' => round($milikNonaktif, 2),
            // Jumlah saldo milik anggota aktif ÷ jumlah anggota aktif; null jika tidak ada anggota aktif.
            'rata_rata_aktif' => $anggotaAktif > 0 ? round(($bisaDitarik + $tertahan) / $anggotaAktif, 2) : null,
            'wajib_masuk_bulan_ini' => round($wajibMasuk, 2),
            'sudah_setor_wajib' => max(0, $anggotaAktif - $jumlahBelumSetorWajib),
            'anggota_aktif' => $anggotaAktif,
        ];
    }

    /**
     * @return array{jumlah_transaksi: int, total_setor: float, total_tarik: float}
     */
    private function aktivitas(Carbon $mulai, Carbon $selesai): array
    {
        $hasil = TransaksiSimpanan::query()
            ->whereDate('tanggal_transaksi', '>=', $mulai->toDateString())
            ->whereDate('tanggal_transaksi', '<=', $selesai->toDateString())
            ->selectRaw("COUNT(*) AS jumlah_transaksi,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->toBase()
            ->first();

        return [
            'jumlah_transaksi' => (int) ($hasil->jumlah_transaksi ?? 0),
            'total_setor' => round((float) ($hasil->total_setor ?? 0), 2),
            'total_tarik' => round((float) ($hasil->total_tarik ?? 0), 2),
        ];
    }

    /**
     * Tren 6 bulan terakhir termasuk bulan ini, urut lama -> baru. Bulan tanpa
     * transaksi tetap muncul bernilai 0. Satu query agregasi per tanggal, lalu
     * dikelompokkan per bulan di PHP (tanpa fungsi tanggal khusus database).
     *
     * @return Collection<int, array{bulan: string, total_setor: float, total_tarik: float}>
     */
    private function trenBulanan(): Collection
    {
        $bulanAwal = now()->startOfMonth()->subMonths(5);

        $perBulan = [];
        for ($i = 0; $i < 6; $i++) {
            $bulan = $bulanAwal->copy()->addMonths($i);
            $perBulan[$bulan->format('Y-m')] = [
                'bulan' => $bulan->translatedFormat('M Y'),
                'total_setor' => 0.0,
                'total_tarik' => 0.0,
            ];
        }

        TransaksiSimpanan::query()
            ->whereDate('tanggal_transaksi', '>=', $bulanAwal->toDateString())
            ->whereDate('tanggal_transaksi', '<=', now()->endOfMonth()->toDateString())
            ->selectRaw("tanggal_transaksi,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->groupBy('tanggal_transaksi')
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$perBulan) {
                $kunci = Carbon::parse($baris->tanggal_transaksi)->format('Y-m');

                if (isset($perBulan[$kunci])) {
                    $perBulan[$kunci]['total_setor'] += (float) $baris->total_setor;
                    $perBulan[$kunci]['total_tarik'] += (float) $baris->total_tarik;
                }
            });

        return collect($perBulan)
            ->map(fn (array $item) => [
                'bulan' => $item['bulan'],
                'total_setor' => round($item['total_setor'], 2),
                'total_tarik' => round($item['total_tarik'], 2),
            ])
            ->values();
    }

    /**
     * Pilihan kartu "Belum setor wajib" (F2d I.4). Parameter `wajib`: kosong = bulan ini,
     * `lalu`, `YYYY-MM` (hanya 12 bulan sebelum bulan lalu), `tunggakan_3|6|12`.
     * Nilai lain diabaikan dan kembali ke bulan ini.
     *
     * @param  Collection<int, Anggota>  $belumBulanIni
     * @return array{pilihan: string, label: string, daftar: Collection<int, Anggota>, jumlah: int, mode: string, kosong: string, opsi_bulan: array<string, string>}
     */
    private function wajib(Request $request, ?int $wajibId, Collection $belumBulanIni, int $jumlahBulanIni): array
    {
        $bulanIni = now()->startOfMonth();
        $opsiBulan = [];
        for ($i = 2; $i <= 13; $i++) {
            $bulan = $bulanIni->copy()->subMonthsNoOverflow($i);
            $opsiBulan[$bulan->format('Y-m')] = $bulan->translatedFormat('F Y');
        }

        $nilai = $request->query('wajib');
        $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';

        if (preg_match('/^tunggakan_(3|6|12)$/', $nilai, $cocok)) {
            $n = (int) $cocok[1];
            [$daftar, $jumlah] = $this->tunggakanWajib($wajibId, $n);

            return [
                'pilihan' => $nilai,
                'label' => "Tunggakan {$n} bulan",
                'daftar' => $daftar,
                'jumlah' => $jumlah,
                'mode' => 'tunggakan',
                'kosong' => "Tidak ada tunggakan dalam {$n} bulan terakhir.",
                'opsi_bulan' => $opsiBulan,
            ];
        }

        $awal = match (true) {
            $nilai === 'lalu' => $bulanIni->copy()->subMonthNoOverflow(),
            isset($opsiBulan[$nilai]) => Carbon::createFromFormat('Y-m-d', $nilai.'-01')->startOfDay(),
            default => null,
        };

        if ($awal === null) {
            return [
                'pilihan' => '',
                'label' => 'Bulan ini',
                'daftar' => $belumBulanIni,
                'jumlah' => $jumlahBulanIni,
                'mode' => 'bulan',
                'kosong' => 'Semua anggota aktif sudah setor wajib bulan ini.',
                'opsi_bulan' => $opsiBulan,
            ];
        }

        $akhir = $awal->copy()->endOfMonth();
        [$daftar, $jumlah] = $this->belumSetorWajib($wajibId, $awal, $akhir, bergabungPalingLambat: $akhir);

        return [
            'pilihan' => $nilai,
            'label' => $nilai === 'lalu' ? 'Bulan lalu' : $awal->translatedFormat('F Y'),
            'daftar' => $daftar,
            'jumlah' => $jumlah,
            'mode' => 'bulan',
            'kosong' => 'Semua anggota aktif sudah setor wajib di '.$awal->translatedFormat('F Y').'.',
            'opsi_bulan' => $opsiBulan,
        ];
    }

    /**
     * Tunggakan wajib N bulan (F2d I.4.3): rentang N bulan yang SUDAH LEWAT (berakhir di bulan lalu).
     * Per anggota aktif, bulan dalam rentang (mulai bulan bergabung jika lebih baru) tanpa setoran
     * Wajib dihitung sebagai "bolong". Dua query; pengelompokan per bulan di PHP.
     *
     * @return array{0: Collection<int, Anggota>, 1: int}
     */
    private function tunggakanWajib(?int $wajibId, int $n): array
    {
        $akhirRentang = now()->startOfMonth()->subMonthNoOverflow()->endOfMonth();
        $awalRentang = now()->startOfMonth()->subMonthsNoOverflow($n);

        $anggota = Anggota::query()
            ->aktif()
            ->whereDate('tanggal_bergabung', '<=', $akhirRentang->toDateString())
            ->orderBy('nama')
            ->get(['id', 'no_anggota', 'nama', 'tanggal_bergabung']);

        /** @var array<int, array<string, true>> $sudah anggota_id => [Y-m => true] */
        $sudah = [];
        TransaksiSimpanan::query()
            ->where('jenis_simpanan_id', $wajibId)
            ->where('jenis_transaksi', TransaksiSimpanan::SETOR)
            ->whereDate('tanggal_transaksi', '>=', $awalRentang->toDateString())
            ->whereDate('tanggal_transaksi', '<=', $akhirRentang->toDateString())
            ->select(['anggota_id', 'tanggal_transaksi'])
            ->distinct()
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$sudah) {
                $sudah[(int) $baris->anggota_id][Carbon::parse($baris->tanggal_transaksi)->format('Y-m')] = true;
            });

        $hasil = $anggota
            ->map(function (Anggota $item) use ($awalRentang, $akhirRentang, $sudah) {
                $bulan = Carbon::parse($item->tanggal_bergabung)->startOfMonth();
                if ($bulan->lt($awalRentang)) {
                    $bulan = $awalRentang->copy();
                }
                $bolong = 0;
                while ($bulan->lte($akhirRentang)) {
                    if (! isset($sudah[$item->id][$bulan->format('Y-m')])) {
                        $bolong++;
                    }
                    $bulan->addMonthNoOverflow();
                }
                $item->setAttribute('bolong', $bolong);

                return $item;
            })
            ->filter(fn (Anggota $item) => $item->bolong >= 1)
            // Paling banyak bolong dulu, lalu nama (koleksi sudah urut nama; pengurutan stabil).
            ->sortByDesc('bolong')
            ->values();

        return [$hasil->take(50)->values(), $hasil->count()];
    }

    /**
     * Anggota aktif yang belum setor wajib bulan ini dan juga belum di bulan lalu
     * (sudah bergabung paling lambat akhir bulan lalu). F2d I.5.2.
     */
    private function jumlahBelumSetorWajibDuaBulan(?int $wajibId): int
    {
        $bulanIni = now()->startOfMonth();
        $bulanLalu = $bulanIni->copy()->subMonthNoOverflow();
        $tanpaWajib = fn (Carbon $awal) => fn ($q) => $q
            ->where('jenis_simpanan_id', $wajibId)
            ->where('jenis_transaksi', TransaksiSimpanan::SETOR)
            ->whereDate('tanggal_transaksi', '>=', $awal->toDateString())
            ->whereDate('tanggal_transaksi', '<=', $awal->copy()->endOfMonth()->toDateString());

        return Anggota::query()
            ->aktif()
            ->whereDate('tanggal_bergabung', '<=', $bulanLalu->copy()->endOfMonth()->toDateString())
            ->whereDoesntHave('transaksiSimpanan', $tanpaWajib($bulanIni))
            ->whereDoesntHave('transaksiSimpanan', $tanpaWajib($bulanLalu))
            ->count();
    }

    /**
     * Anggota aktif yang belum setor Simpanan Wajib di bulan tertentu. Informasi
     * saja, bukan aturan bisnis. Daftar maksimal 50 (F2d F.4, H.2.3); jumlah tetap penuh.
     * Bulan lalu (F2d F.2): hanya anggota yang sudah bergabung paling lambat akhir bulan itu.
     *
     * @return array{0: Collection<int, Anggota>, 1: int}
     */
    private function belumSetorWajib(?int $wajibId, Carbon $awalBulan, Carbon $akhirBulan, ?Carbon $bergabungPalingLambat = null): array
    {
        $query = Anggota::query()
            ->aktif()
            ->when($bergabungPalingLambat, fn ($q, Carbon $batas) => $q->whereDate('tanggal_bergabung', '<=', $batas->toDateString()))
            ->whereDoesntHave('transaksiSimpanan', fn ($q) => $q
                ->where('jenis_simpanan_id', $wajibId)
                ->where('jenis_transaksi', TransaksiSimpanan::SETOR)
                ->whereDate('tanggal_transaksi', '>=', $awalBulan->toDateString())
                ->whereDate('tanggal_transaksi', '<=', $akhirBulan->toDateString()));

        return [
            (clone $query)->orderBy('nama')->limit(50)->get(['id', 'no_anggota', 'nama']),
            $query->count(),
        ];
    }
}
