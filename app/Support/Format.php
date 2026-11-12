<?php

namespace App\Support;

use Carbon\Carbon;

class Format
{
    public static function rupiah(float|int|string|null $nilai): string
    {
        return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
    }

    public static function tanggal($tanggal): string
    {
        if ($tanggal === null) {
            return '-';
        }

        return Carbon::parse($tanggal)->translatedFormat('d F Y');
    }
}
