<?php

namespace Tests\Unit;

use App\Support\Format;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Meng-extend Tests\TestCase agar locale `id` dan timezone aplikasi aktif (F1 §4.2).
 */
class FormatTest extends TestCase
{
    public function test_hari_tanggal_memakai_nama_hari_bahasa_indonesia(): void
    {
        $this->assertSame('Senin, 16 November 2026', Format::hariTanggal('2026-11-16'));
        $this->assertSame('Minggu, 15 November 2026', Format::hariTanggal('2026-11-15'));
    }

    public function test_hari_tanggal_null_menjadi_strip(): void
    {
        $this->assertSame('-', Format::hariTanggal(null));
    }

    /**
     * F2b D.1: batas jam WITA pagi 04.00–10.59, siang 11.00–14.59, sore 15.00–17.59, malam 18.00–03.59.
     */
    public function test_sapaan_mengikuti_batas_jam_wita(): void
    {
        $kasus = [
            '03:59:59' => 'Selamat malam',
            '04:00:00' => 'Selamat pagi',
            '10:59:59' => 'Selamat pagi',
            '11:00:00' => 'Selamat siang',
            '14:59:59' => 'Selamat siang',
            '15:00:00' => 'Selamat sore',
            '17:59:59' => 'Selamat sore',
            '18:00:00' => 'Selamat malam',
            '00:00:00' => 'Selamat malam',
        ];

        foreach ($kasus as $jam => $harapan) {
            $this->assertSame($harapan, Format::sapaan(Carbon::parse('2026-11-12 '.$jam)), "Jam {$jam}");
        }
    }

    public function test_sapaan_memakai_zona_waktu_aplikasi(): void
    {
        // 02.30 UTC = 10.30 WITA (pagi); 03.30 UTC = 11.30 WITA (siang).
        $this->assertSame('Selamat pagi', Format::sapaan(Carbon::parse('2026-11-12 02:30:00', 'UTC')));
        $this->assertSame('Selamat siang', Format::sapaan(Carbon::parse('2026-11-12 03:30:00', 'UTC')));
    }
}
