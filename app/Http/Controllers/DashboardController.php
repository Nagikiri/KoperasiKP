<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\GrafikService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard ringkasan (FR-20, diperluas revisi R3). Semua angka dari query
     * agregasi; tidak ada N+1. `grafikArus` ditambahkan di F2b (A.2).
     */
    public function index(SimpananService $simpananService, GrafikService $grafikService): View
    {
        $hariIni = now()->startOfDay();
        $awalBulan = now()->startOfMonth();
        $akhirBulan = now()->endOfMonth();

        $rekapSaldo = $simpananService->rekapPerJenis();

        [$belumSetorWajib, $jumlahBelumSetorWajib] = $this->belumSetorWajib($awalBulan, $akhirBulan);

        return view('dashboard', [
            'ringkasanAnggota' => $this->ringkasanAnggota($awalBulan, $akhirBulan),
            'rekapSaldo' => $rekapSaldo,
            'totalSaldo' => round((float) $rekapSaldo->sum('saldo'), 2),
            'aktivitasHariIni' => $this->aktivitas($hariIni, $hariIni),
            'aktivitasBulanIni' => $this->aktivitas($awalBulan, $akhirBulan),
            'trenBulanan' => $this->trenBulanan(),
            'belumSetorWajib' => $belumSetorWajib,
            'jumlahBelumSetorWajib' => $jumlahBelumSetorWajib,
            'transaksiTerbaru' => TransaksiSimpanan::with(['anggota', 'jenisSimpanan', 'staff'])
                ->orderByDesc('tanggal_transaksi')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'grafikArus' => $grafikService->arusSimpanan(),
        ]);
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
     * Anggota aktif yang belum setor Simpanan Wajib di bulan berjalan. Informasi
     * saja, bukan aturan bisnis.
     *
     * @return array{0: Collection<int, Anggota>, 1: int}
     */
    private function belumSetorWajib(Carbon $awalBulan, Carbon $akhirBulan): array
    {
        $wajibId = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->value('id');

        $query = Anggota::query()
            ->aktif()
            ->whereDoesntHave('transaksiSimpanan', fn ($q) => $q
                ->where('jenis_simpanan_id', $wajibId)
                ->where('jenis_transaksi', TransaksiSimpanan::SETOR)
                ->whereDate('tanggal_transaksi', '>=', $awalBulan->toDateString())
                ->whereDate('tanggal_transaksi', '<=', $akhirBulan->toDateString()));

        return [
            (clone $query)->orderBy('nama')->limit(10)->get(['id', 'no_anggota', 'nama']),
            $query->count(),
        ];
    }
}
