<?php

use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicController;
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
});

require __DIR__.'/auth.php';
