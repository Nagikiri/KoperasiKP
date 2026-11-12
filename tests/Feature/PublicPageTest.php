<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_publik_tampil_untuk_tamu_tanpa_query_database(): void
    {
        DB::enableQueryLog();

        $this->get('/')
            ->assertOk()
            ->assertSee('KSP Karya Duta Utama');

        $this->assertCount(0, DB::getQueryLog());
    }
}
