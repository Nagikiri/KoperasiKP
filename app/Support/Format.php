<?php

namespace App\Support;

use App\Models\Anggota;
use Carbon\Carbon;

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

    public static function statusAnggota(string $status): string
    {
        return $status === Anggota::STATUS_AKTIF ? 'Aktif' : 'Nonaktif';
    }
}
