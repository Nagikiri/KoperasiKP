<?php

use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TransaksiSimpananController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicController::class, 'index'])->name('public.home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Anggota: route eksplisit (bukan Route::resource) agar parameter tetap {anggota}.
    Route::get('/anggota', [AnggotaController::class, 'index'])->name('anggota.index');
    Route::get('/anggota/create', [AnggotaController::class, 'create'])->name('anggota.create');
    Route::post('/anggota', [AnggotaController::class, 'store'])->name('anggota.store');
    Route::get('/anggota/{anggota}', [AnggotaController::class, 'show'])
        ->whereNumber('anggota')->name('anggota.show');
    Route::get('/anggota/{anggota}/edit', [AnggotaController::class, 'edit'])
        ->whereNumber('anggota')->name('anggota.edit');
    Route::put('/anggota/{anggota}', [AnggotaController::class, 'update'])
        ->whereNumber('anggota')->name('anggota.update');
    Route::patch('/anggota/{anggota}/nonaktifkan', [AnggotaController::class, 'nonaktifkan'])
        ->whereNumber('anggota')->name('anggota.nonaktifkan');

    // Transaksi simpanan: hanya catat dan lihat, tanpa ubah/hapus (BR-11).
    Route::get('/transaksi/setor', [TransaksiSimpananController::class, 'createSetor'])->name('transaksi.setor.create');
    Route::post('/transaksi/setor', [TransaksiSimpananController::class, 'storeSetor'])->name('transaksi.setor.store');
    Route::get('/transaksi/tarik', [TransaksiSimpananController::class, 'createTarik'])->name('transaksi.tarik.create');
    Route::post('/transaksi/tarik', [TransaksiSimpananController::class, 'storeTarik'])->name('transaksi.tarik.store');
    Route::get('/transaksi/riwayat', [TransaksiSimpananController::class, 'riwayat'])->name('transaksi.riwayat');
    // F5 H.5: ekspor Riwayat dengan filter yang sama seperti halaman.
    Route::get('/transaksi/riwayat/pdf', [TransaksiSimpananController::class, 'pdfRiwayat'])->name('transaksi.riwayat.pdf');
    Route::get('/transaksi/riwayat/excel', [TransaksiSimpananController::class, 'excelRiwayat'])->name('transaksi.riwayat.excel');

    // Laporan: per anggota dan rekap & laporan keseluruhan, plus ekspor (satu sumber data LaporanService).
    Route::get('/laporan/anggota', [LaporanController::class, 'anggota'])->name('laporan.anggota');
    Route::get('/laporan/anggota/pdf', [LaporanController::class, 'pdf'])->name('laporan.anggota.pdf');
    Route::get('/laporan/anggota/excel', [LaporanController::class, 'excel'])->name('laporan.anggota.excel');
    Route::get('/laporan/keseluruhan', [LaporanController::class, 'keseluruhan'])->name('laporan.keseluruhan');
    Route::get('/laporan/keseluruhan/pdf', [LaporanController::class, 'pdfKeseluruhan'])->name('laporan.keseluruhan.pdf');
    Route::get('/laporan/keseluruhan/excel', [LaporanController::class, 'excelKeseluruhan'])->name('laporan.keseluruhan.excel');
});

require __DIR__.'/auth.php';
