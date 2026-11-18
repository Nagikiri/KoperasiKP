<?php

namespace App\Support;

use App\Models\Anggota;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class Format
{
    public static function rupiah(float|int|string|null $nilai): string
    {
        return 'Rp '.number_format((float) $nilai, 0, ',', '.');
    }

    public static function tanggal($tanggal): string
    {
        if ($tanggal === null) {
            return '-';
        }

        return Carbon::parse($tanggal)->translatedFormat('j F Y');
    }

    /**
     * Contoh: `Senin, 16 November 2026` (topbar, F1 §4.2).
     */
    public static function hariTanggal($tanggal): string
    {
        if ($tanggal === null) {
            return '-';
        }

        return Carbon::parse($tanggal)->translatedFormat('l, j F Y');
    }

    /**
     * Sapaan dashboard sesuai jam WITA (F2b D.1): pagi 04.00–10.59, siang 11.00–14.59,
     * sore 15.00–17.59, malam 18.00–03.59. Contoh: `Selamat pagi`.
     */
    public static function sapaan(Carbon $waktu): string
    {
        $jam = $waktu->copy()->setTimezone(config('app.timezone'))->hour;

        $bagian = match (true) {
            $jam >= 4 && $jam < 11 => 'pagi',
            $jam >= 11 && $jam < 15 => 'siang',
            $jam >= 15 && $jam < 18 => 'sore',
            default => 'malam',
        };

        return 'Selamat '.$bagian;
    }

    /**
     * Contoh: `12 November 2026 14.30 WITA`.
     */
    public static function tanggalWaktu($waktu): string
    {
        return Carbon::parse($waktu)->translatedFormat('j F Y H.i').' WITA';
    }

    /**
     * Nama anggota untuk teks polos (dropdown, Excel): `Budi (Nonaktif)` bila nonaktif (BR-21).
     */
    public static function namaAnggota(string $nama, string $status): string
    {
        return $status === Anggota::STATUS_AKTIF ? $nama : $nama.' (Nonaktif)';
    }

    /**
     * Lama sejak suatu tanggal, relatif ke hari ini (kartu Identitas detail anggota). Contoh: `11 hari yang lalu`, `2 bulan yang lalu`.
     * Dihitung dari awal hari supaya tanggal tanpa jam tidak terbaca "10 jam yang lalu"; tanggal hari ini = `hari ini`.
     */
    public static function sejak($tanggal): string
    {
        if ($tanggal === null) {
            return '-';
        }

        $tanggal = Carbon::parse($tanggal)->startOfDay();
        $hariIni = now()->startOfDay();

        if ($tanggal->equalTo($hariIni)) {
            return 'hari ini';
        }

        // Satuan minggu dilewati supaya terbaca "11 hari yang lalu", bukan "1 minggu yang lalu".
        return $tanggal->diffForHumans($hariIni, [
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
            'skip' => ['week'],
        ]);
    }

    /**
     * NIK disamarkan untuk halaman daftar (F3b B.6): 4 digit awal + 8 titik + 4 digit akhir.
     * Contoh: `6471••••••••5870`. NIK penuh tetap tampil di detail dan form.
     */
    public static function nikSamar(?string $nik): string
    {
        $nik = trim((string) $nik);

        if (strlen($nik) <= 8) {
            return str_repeat('•', strlen($nik));
        }

        return substr($nik, 0, 4).str_repeat('•', 8).substr($nik, -4);
    }

    public static function statusAnggota(string $status): string
    {
        return $status === Anggota::STATUS_AKTIF ? 'Aktif' : 'Nonaktif';
    }
}
