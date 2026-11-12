<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\TransaksiSimpanan;
use App\Services\SimpananService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(SimpananService $simpananService): View
    {
        $rekapSaldo = $simpananService->rekapPerJenis();

        return view('dashboard', [
            'totalAnggotaAktif' => Anggota::aktif()->count(),
            'rekapSaldo' => $rekapSaldo,
            'totalSaldo' => round((float) $rekapSaldo->sum('saldo'), 2),
            'transaksiTerbaru' => TransaksiSimpanan::with(['anggota', 'jenisSimpanan', 'staff'])
                ->orderByDesc('tanggal_transaksi')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
