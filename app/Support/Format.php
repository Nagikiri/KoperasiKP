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
