<?php

namespace Tests\Unit;

use App\Support\Format;
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
}
