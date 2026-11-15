<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\User;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 §7.1: layout admin (sidebar, topbar + menu akun, flash), halaman login,
 * halaman Pengaturan Akun, dan logo.
 */
class TampilanDasarTest extends TestCase
{
    use RefreshDatabase;

    private const LABEL_MENU = ['Dashboard', 'Data Anggota', 'Setor', 'Tarik', 'Riwayat Transaksi', 'Rekap Keseluruhan', 'Laporan per Anggota'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi', 'username' => 'admin', 'no_telepon' => null]);
    }

    /**
     * @return array<string, string> [nama route => URL]
     */
    private function halamanAdmin(): array
    {
        $anggota = Anggota::factory()->create();

        return [
            'dashboard' => route('dashboard'),
            'anggota.index' => route('anggota.index'),
            'anggota.create' => route('anggota.create'),
            'anggota.show' => route('anggota.show', $anggota),
            'anggota.edit' => route('anggota.edit', $anggota),
            'transaksi.setor.create' => route('transaksi.setor.create'),
            'transaksi.tarik.create' => route('transaksi.tarik.create'),
            'transaksi.riwayat' => route('transaksi.riwayat'),
            'laporan.keseluruhan' => route('laporan.keseluruhan'),
            'laporan.anggota' => route('laporan.anggota'),
            'password.edit' => route('password.edit'),
        ];
    }

    private function bagian(string $html, string $tag, string $jangkar): string
    {
        $this->assertSame(1, preg_match('/<'.$tag.' '.$jangkar.'\b.*?<\/'.$tag.'>/s', $html, $cocok), "Elemen <{$tag} {$jangkar}> tidak ditemukan.");

        return $cocok[0];
    }

    // ---------------------------------------------------------------
    // Halaman login
    // ---------------------------------------------------------------

    public function test_halaman_login_tampil_dengan_identitas_koperasi(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('KSP Karya Duta Utama')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('data-lihat-kata-sandi', false)
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee('data-pola-latar', false);

        // L4: teks lupa kata sandi tepat di bawah tombol Masuk.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*>\s*Masuk\s*<\/button>\s*<p[^>]*>Lupa kata sandi\? Hubungi pengurus koperasi\.<\/p>/',
            $this->get(route('login'))->getContent(),
        );
    }

    public function test_login_tanpa_tiga_ciri_dan_footer_tunggal_di_kolom_kiri(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        // L3: tiga ciri berikon dihapus.
        $this->assertStringNotContainsString('data-ciri-login', $html);
        foreach (['Data anggota', 'Setor &amp; tarik', 'Laporan PDF/Excel'] as $ciri) {
            $this->assertStringNotContainsString($ciri, $html);
        }

        // L5: footer satu baris di kolom kiri; © tidak dobel di panel kanan.
        $tahun = now()->year;
        $this->assertStringContainsString('<p data-footer-login class="pt-8 text-center text-xs text-stone-400">Khusus pengurus KSP Karya Duta Utama &middot; &copy; '.$tahun.'</p>', $html);
        $this->assertSame(1, substr_count($html, '&copy;'));
    }

    public function test_panel_login_memakai_foto_bila_ada(): void
    {
        $html = $this->get(route('login'))->getContent();

        if (file_exists(public_path('images/login-bg.jpg'))) {
            $this->assertStringContainsString('data-panel-latar="foto"', $html);
            $this->assertStringContainsString('images/login-bg.jpg', $html);
        } else {
            $this->assertStringContainsString('data-panel-latar="pola"', $html);
        }
    }

    public function test_panel_login_dan_logo_memakai_cadangan_bila_gambar_tidak_ada(): void
    {
        // Arahkan public_path() ke folder yang tidak ada: file gambar asli tidak dibuat atau dihapus.
        $this->app->usePublicPath(storage_path('framework/testing/tanpa-gambar'));

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('data-panel-latar="pola"', $html);
        $this->assertStringContainsString('data:image/svg+xml', $html);
        $this->assertStringNotContainsString('images/login-bg.jpg', $html);
        $this->assertStringContainsString('KDU', $html);
        $this->assertStringNotContainsString('alt="Lambang Koperasi Indonesia"', $html);
    }

    // ---------------------------------------------------------------
    // Layout admin: sidebar dan topbar
    // ---------------------------------------------------------------

    public function test_setiap_halaman_admin_memuat_sidebar_navigasi_dan_menu_akun_di_topbar(): void
    {
        foreach ($this->halamanAdmin() as $nama => $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $sidebar = $this->bagian($html, 'aside', 'data-sidebar');
            $topbar = $this->bagian($html, 'header', 'data-topbar');

            foreach (self::LABEL_MENU as $label) {
                $this->assertStringContainsString($label, $sidebar, "Label menu \"{$label}\" tidak ada di sidebar {$nama}.");
            }
            foreach (['Anggota', 'Transaksi', 'Laporan'] as $grup) {
                $this->assertStringContainsString('data-menu-grup="'.$grup.'"', $sidebar, "Grup {$grup} tidak ada di {$nama}.");
            }
            $this->assertStringNotContainsString('Keluar', $sidebar, "Sidebar {$nama} tidak boleh memuat Keluar.");
            $this->assertStringNotContainsString('Pengaturan Akun', $sidebar, "Sidebar {$nama} tidak boleh memuat Pengaturan Akun.");

            $this->assertStringContainsString('data-menu-akun', $topbar);
            $this->assertStringContainsString('Admin Koperasi', $topbar);
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('password.edit'), '/').'"[^>]*>.*?Pengaturan Akun/s', $topbar);
            $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('logout'), '/').'">/', $topbar);
            $this->assertStringContainsString('Keluar', $topbar);

            $this->assertStringNotContainsString('onsubmit=', $sidebar.$topbar);
            $this->assertSame(1, substr_count($html, 'data-cegah-kirim-ganda'), "Skrip cegah kirim ganda harus tepat satu kali di {$nama}.");
        }
    }

    public function test_item_menu_aktif_ditandai_aria_current_di_halamannya(): void
    {
        $anggota = Anggota::factory()->create();

        $kasus = [
            // [URL halaman, route tautan menu yang harus aktif]
            [route('dashboard'), 'dashboard'],
            [route('anggota.index'), 'anggota.index'],
            [route('anggota.show', $anggota), 'anggota.index'],
            [route('transaksi.setor.create'), 'transaksi.setor.create'],
            [route('transaksi.tarik.create'), 'transaksi.tarik.create'],
            [route('transaksi.riwayat'), 'transaksi.riwayat'],
            [route('laporan.keseluruhan'), 'laporan.keseluruhan'],
            [route('laporan.anggota'), 'laporan.anggota'],
            [route('password.edit'), 'password.edit'],
        ];

        foreach ($kasus as [$url, $routeMenu]) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route($routeMenu), '/').'"[^>]*aria-current="page"/', $html, "Menu {$routeMenu} harus aktif di {$url}.");
            $this->assertSame(1, substr_count($html, 'aria-current="page"'), "Hanya satu item aktif di {$url}.");
        }
    }

    // ---------------------------------------------------------------
    // Halaman Pengaturan Akun
    // ---------------------------------------------------------------

    public function test_pengaturan_akun_memuat_kartu_profil_dan_ganti_password(): void
    {
        $this->assertSame(url('/pengaturan-akun'), route('password.edit'));

        $html = $this->actingAs($this->admin)->get(route('password.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('Pengaturan Akun', $this->bagian($html, 'header', 'data-topbar'));
        $this->assertStringContainsString('Profil dan keamanan akun staff.', $html);

        $this->assertSame(1, preg_match('/data-kartu-profil(.*?)data-kartu-ganti-password/s', $html, $cocok));
        $profil = $cocok[1];
        foreach (['Profil', 'Admin Koperasi', 'admin', 'No. telepon', '–', 'Perubahan data profil dilakukan oleh pengelola sistem.'] as $teks) {
            $this->assertStringContainsString($teks, $profil);
        }

        $this->assertMatchesRegularExpression('/data-kartu-ganti-password.*?<h2[^>]*>Ganti Password<\/h2>.*?name="current_password"/s', $html);
    }

    public function test_pengaturan_akun_menampilkan_no_telepon_bila_ada(): void
    {
        $this->admin->update(['no_telepon' => '081234567890']);

        $this->actingAs($this->admin)->get(route('password.edit'))
            ->assertOk()
            ->assertSee('081234567890');
    }

    public function test_uri_ganti_password_lama_sudah_tidak_ada(): void
    {
        $this->actingAs($this->admin)->get('/ganti-password')->assertNotFound();
    }

    public function test_flash_sukses_tampil_di_layout_setelah_ganti_password(): void
    {
        $this->actingAs($this->admin)
            ->from(route('password.edit'))
            ->followingRedirects()
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee('Kata sandi berhasil diubah.');
    }

    public function test_ganti_password_gagal_tanpa_flash_dan_error_tampil_per_field(): void
    {
        $this->actingAs($this->admin)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'bukan-password-lama',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $pesan = session('errors')->getBag('updatePassword')->first('current_password');

        $this->actingAs($this->admin)->get(route('password.edit'))
            ->assertOk()
            ->assertDontSee('data-flash', false)
            ->assertSee('<p class="text-xs text-red-700 mt-1.5">'.e($pesan).'</p>', false);
    }

    // ---------------------------------------------------------------
    // Logo
    // ---------------------------------------------------------------

    public function test_logo_atau_cadangan_kdu_tampil(): void
    {
        $adaLogo = file_exists(public_path('images/logo-koperasi.png'));

        $halaman = [
            $this->get(route('login'))->getContent(),
            $this->actingAs($this->admin)->get(route('dashboard'))->getContent(),
        ];

        foreach ($halaman as $html) {
            if ($adaLogo) {
                $this->assertMatchesRegularExpression('/<img[^>]*alt="Lambang Koperasi Indonesia"/', $html);
            } else {
                $this->assertStringContainsString('KDU', $html);
                $this->assertStringNotContainsString('alt="Lambang Koperasi Indonesia"', $html);
            }
        }
    }
}
