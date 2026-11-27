<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R1 G.2: poles anti-template (sudut, bayangan, avatar, ikon di tombol, font, sebaran simpanan, tab garis bawah).
 * Pemeriksaan bentuk dibatasi ke <main> (sidebar dan topbar berisi logo/avatar akun bulat yang sengaja dipertahankan).
 */
class PolesTampilanTest extends TestCase
{
    use RefreshDatabase;

    /** Ukuran titik warna yang boleh rounded-full (R1 C.1 pengecualian). */
    private const UKURAN_TITIK = [['h-1.5', 'w-1.5'], ['h-2', 'w-2'], ['h-2.5', 'w-2.5']];

    private User $admin;

    private Anggota $budi;

    private Anggota $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);
        $this->budi = Anggota::factory()->create(['nama' => 'Budi Santoso', 'tanggal_bergabung' => '2026-01-01']);
        $this->andi = Anggota::factory()->nonaktif()->create(['nama' => 'Andi Wijaya', 'tanggal_bergabung' => '2026-01-01']);
        Anggota::factory()->create(['nama' => 'Citra Lestari', 'tanggal_bergabung' => '2026-01-01']);

        $this->buatTransaksi($this->budi, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-10-01');
        $this->buatTransaksi($this->budi, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-05');
        $this->buatTransaksi($this->budi, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-11-06');
        $this->buatTransaksi($this->budi, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 40000, '2026-11-10');
        $this->buatTransaksi($this->andi, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 70000, '2026-02-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Halaman utama R1 G.2. */
    private function halamanUtama(): array
    {
        return [
            'dashboard' => route('dashboard'),
            'daftar anggota' => route('anggota.index'),
            'detail anggota' => route('anggota.show', $this->budi),
            'setor' => route('transaksi.setor.create'),
            'tarik' => route('transaksi.tarik.create'),
            'riwayat' => route('transaksi.riwayat'),
            'laporan per anggota' => route('laporan.anggota', ['tampilkan' => '1']),
            'laporan keseluruhan' => route('laporan.keseluruhan'),
        ];
    }

    public function test_tidak_ada_rounded_full_rounded_2xl_atau_bayangan_di_halaman_utama_kecuali_titik_warna(): void
    {
        foreach ($this->halamanUtama() as $nama => $url) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get($url)->assertOk()->getContent());
            $main = $xpath->query('//main')->item(0);
            $this->assertNotNull($main, $nama);

            foreach ($xpath->query('.//*[@class]', $main) as $el) {
                $kelas = preg_split('/\s+/', trim($el->getAttribute('class')));

                $this->assertNotContains('rounded-2xl', $kelas, "{$nama}: rounded-2xl");
                $this->assertNotContains('rounded-xl', $kelas, "{$nama}: rounded-xl");

                if (in_array('rounded-full', $kelas, true)) {
                    $titik = collect(self::UKURAN_TITIK)->contains(fn ($ukuran) => ! array_diff($ukuran, $kelas));
                    $this->assertTrue($titik, "{$nama}: rounded-full hanya untuk titik warna, ditemukan di class=\"".implode(' ', $kelas).'"');
                }

                // Bayangan hanya untuk penanda geser tabel Riwayat (kolom menempel dan petunjuk tepi kanan), keputusan R1.
                $bayangan = array_filter($kelas, fn ($k) => preg_match('/(^|:)shadow(-|$)/', $k));
                if ($bayangan) {
                    $this->assertTrue(
                        $el->hasAttribute('data-kolom-tempel') || $el->hasAttribute('data-petunjuk-geser'),
                        "{$nama}: bayangan ".implode(' ', $bayangan)
                    );
                }
            }
        }
    }

    public function test_tidak_ada_avatar_inisial_di_tabel_dan_daftar(): void
    {
        foreach ($this->halamanUtama() as $nama => $url) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get($url)->getContent());
            // Avatar inisial = span aria-hidden bulat berisi huruf (komponen x-ui.avatar-initial).
            $avatar = $xpath->query('//main//span[@aria-hidden="true"][contains(concat(" ", @class, " "), " rounded-full ")][contains(@class, "font-semibold")]');
            $this->assertSame(0, $avatar->length, $nama);
        }

        // Avatar akun di topbar tetap ada (R1 C.6).
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame(1, $xpath->query('//header//span[@aria-hidden="true"][contains(@class, "rounded-full")][contains(@class, "font-semibold")]')->length);
    }

    public function test_tombol_berteks_tanpa_ikon(): void
    {
        $teksTombol = ['Tambah anggota', 'Catat setoran', 'Catat penarikan', 'Unduh PDF', 'Unduh Excel', 'Batal', 'Tampilkan',
            'Simpan anggota', 'Simpan setoran', 'Simpan penarikan'];
        $halaman = $this->halamanUtama() + [
            'tambah anggota' => route('anggota.create'),
            'laporan per anggota (pratinjau)' => route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => $this->budi->id]),
        ];

        $ditemukan = [];
        foreach ($halaman as $nama => $url) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get($url)->getContent());
            foreach ($xpath->query('//main//a | //main//button') as $tombol) {
                $teks = trim(preg_replace('/\s+/', ' ', $tombol->textContent));
                if (in_array($teks, $teksTombol, true)) {
                    $ditemukan[$teks] = true;
                    $this->assertSame(0, $xpath->query('.//*[local-name()="svg"]', $tombol)->length, "{$nama}: tombol \"{$teks}\" tidak boleh berikon.");
                }
            }
        }
        $this->assertEqualsCanonicalizing($teksTombol, array_keys($ditemukan), 'Semua tombol berteks yang diperiksa memang ditemukan.');

        // Tombol "Unduh" di Riwayat hanya membawa chevron dropdown (R1 B.4), bukan ikon dokumen.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent());
        $svg = $xpath->query('//*[@data-unduh-riwayat]/button//*[local-name()="svg"]');
        $this->assertSame(1, $svg->length);
        $this->assertSame('m6 9 6 6 6-6', $xpath->query('./*[local-name()="path"]', $svg->item(0))->item(0)->getAttribute('d'));
    }

    public function test_ikon_lucide_dengan_atribut_svg_baku_dan_nama_lama_tetap_dikenali(): void
    {
        $render = fn (string $nama) => view('components.ui.icon', ['name' => $nama, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();

        $svg = $render('magnifying-glass');
        foreach (['viewBox="0 0 24 24"', 'fill="none"', 'stroke="currentColor"', 'stroke-width="1.75"', 'stroke-linecap="round"', 'stroke-linejoin="round"', 'aria-hidden="true"'] as $atribut) {
            $this->assertStringContainsString($atribut, $svg);
        }
        // Isi Lucide "search": path + circle (bukan path tunggal Heroicons).
        $this->assertStringContainsString('<circle cx="11" cy="11" r="8" />', $svg);
        $this->assertSame('', trim($render('tidak-ada')));
        $this->assertStringContainsString('Lucide', file_get_contents(resource_path('views/components/ui/icon.blade.php')));
        $this->assertStringContainsString('ISC', file_get_contents(resource_path('views/components/ui/icon.blade.php')));
    }

    public function test_font_ibm_plex_dimuat_di_layout_app_dan_guest(): void
    {
        // Layout guest (login, sebagai tamu) lalu layout app (dashboard).
        $halaman = [$this->get(route('login'))->assertOk()->getContent(), $this->actingAs($this->admin)->get(route('dashboard'))->getContent()];
        foreach ($halaman as $html) {
            $this->assertMatchesRegularExpression('#<link href="https://fonts\.bunny\.net/css\?family=ibm-plex-sans:400,500,600\|ibm-plex-mono:400,500&(amp;)?display=swap" rel="stylesheet"#', $html);
            $this->assertStringNotContainsString('family=inter', $html);
        }

        $tailwind = file_get_contents(base_path('tailwind.config.js'));
        $this->assertStringContainsString("sans: ['\"IBM Plex Sans\"'", $tailwind);
        $this->assertStringContainsString("mono: ['\"IBM Plex Mono\"'", $tailwind);
        $this->assertStringContainsString("Chart.defaults.font.family = \"'IBM Plex Sans'", file_get_contents(resource_path('js/grafik/arus-simpanan.js')));
    }

    public function test_kondisi_saldo_berjumlah_sama_dengan_total_dengan_label_tertahan_anggota_aktif(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());
        $kondisi = $xpath->query('//*[@data-kondisi-saldo]')->item(0);
        $this->assertNotNull($kondisi);

        $label = [];
        $jumlah = 0;
        foreach ($xpath->query('.//*[@data-ringkasan-baris]', $kondisi) as $baris) {
            $label[] = trim($xpath->query('.//*[@data-label-kondisi]', $baris)->item(0)->textContent);
            $jumlah += (int) preg_replace('/\D/', '', $xpath->query('./dd', $baris)->item(0)->textContent);
            // Penanda kotak 8 px (bukan titik bulat).
            $penanda = explode(' ', $xpath->query('.//*[@data-penanda-kondisi]', $baris)->item(0)->getAttribute('class'));
            $this->assertContains('h-2', $penanda);
            $this->assertContains('w-2', $penanda);
            $this->assertNotContains('rounded-full', $penanda);
        }
        $this->assertSame(['Bisa ditarik', 'Tertahan (anggota aktif)', 'Milik anggota nonaktif'], $label);
        $this->assertSame((int) round($response->viewData('totalSaldo')), $jumlah);
        $this->assertSame('Rp 480.000', trim($xpath->query('//*[@data-komposisi-total]/p[2]')->item(0)->textContent));

        // Bar tipis terbagi sesuai proporsi; lebar bagian berjumlah 100%.
        $bar = $xpath->query('.//*[@data-bar-kondisi]', $kondisi)->item(0);
        $this->assertContains('h-1.5', explode(' ', $bar->getAttribute('class')));
        $this->assertContains('rounded-sm', explode(' ', $bar->getAttribute('class')));
        $lebar = [];
        foreach ($xpath->query('./*[@data-bagian-kondisi]', $bar) as $bagian) {
            preg_match('/width:\s*([\d.]+)%/', $bagian->getAttribute('style'), $cocok);
            $lebar[$bagian->getAttribute('data-bagian-kondisi')] = (float) $cocok[1];
        }
        // Budi: sukarela 260.000 (bisa ditarik), pokok+wajib 150.000 (tertahan); Andi nonaktif 70.000.
        $this->assertEqualsWithDelta(['bisa_ditarik' => 54.17, 'tertahan' => 31.25, 'milik_nonaktif' => 14.58], $lebar, 0.01);
        $this->assertEqualsWithDelta(100.0, array_sum($lebar), 0.02);

        // Tanpa milik nonaktif: baris ketiga disembunyikan.
        TransaksiSimpanan::query()->where('anggota_id', $this->andi->id)->delete();
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame(['bisa_ditarik', 'tertahan'], array_map(fn ($n) => $n->getAttribute('data-ringkasan-baris'), iterator_to_array($xpath->query('//*[@data-kondisi-saldo]//*[@data-ringkasan-baris]'))));
    }

    public function test_popover_info_kondisi_saldo_punya_aria_describedby_dan_bisa_ditutup_esc(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $harapan = [
            'bisa_ditarik' => 'Saldo sukarela milik anggota aktif. Bisa ditarik kapan saja.',
            'tertahan' => 'Simpanan pokok dan wajib milik anggota aktif. Tidak bisa ditarik selama masih anggota.',
            'milik_nonaktif' => 'Semua simpanan milik anggota yang sudah nonaktif.',
        ];
        $tombol = $xpath->query('//*[@data-kondisi-saldo]//button[@data-info-kondisi]');
        $this->assertSame(3, $tombol->length);
        foreach ($tombol as $el) {
            $kunci = $el->getAttribute('data-info-kondisi');
            $id = $el->getAttribute('aria-describedby');
            $this->assertNotSame('', $id, $kunci);
            $popover = $xpath->query('//*[@id="'.$id.'"]');
            $this->assertSame(1, $popover->length, $kunci);
            $this->assertSame('tooltip', $popover->item(0)->getAttribute('role'));
            $this->assertSame($harapan[$kunci], trim($popover->item(0)->textContent));
            $this->assertFalse($el->hasAttribute('title'), 'Bukan hanya title.');

            // Muncul saat hover dan fokus, tertutup saat Esc.
            foreach (['x-on:mouseenter' => 'buka = true', 'x-on:focus' => 'buka = true', 'x-on:keydown.escape' => 'buka = false', 'x-on:blur' => 'buka = false'] as $atribut => $nilai) {
                $this->assertSame($nilai, $el->getAttribute($atribut), "{$kunci}: {$atribut}");
            }
            $this->assertSame('buka', $popover->item(0)->getAttribute('x-show'));

            // Ikon Lucide "info" 14 px abu.
            $ikon = $xpath->query('./*[local-name()="svg"]', $el)->item(0);
            $this->assertStringContainsString('h-3.5 w-3.5', $ikon->getAttribute('class'));
            $this->assertSame(1, $xpath->query('./*[local-name()="circle"][@r="10"]', $ikon)->length);
            $this->assertStringContainsString('text-stone-400', $el->getAttribute('class'));
        }
    }

    public function test_kotak_wajib_berisi_progres_teks_sudah_dari_aktif_dan_tautan_belum_setor(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $info = $xpath->query('//*[@data-info-anggota]')->item(0);
        $this->assertSame('Informasi anggota', trim($xpath->query('./h3', $info)->item(0)->textContent));

        // Budi sudah setor wajib bulan ini; Citra belum. Andi nonaktif tidak dihitung.
        $kotak = $xpath->query('.//*[@data-ringkasan-baris="wajib_masuk_bulan_ini"]', $info)->item(0);
        $this->assertContains('rounded-md', explode(' ', $kotak->getAttribute('class')));
        $this->assertStringNotContainsString('shadow', $kotak->getAttribute('class'));
        $progres = $xpath->query('.//*[@data-progres-wajib]', $kotak)->item(0);
        $this->assertSame('progressbar', $progres->getAttribute('role'));
        $this->assertSame(['1', '2'], [$progres->getAttribute('aria-valuenow'), $progres->getAttribute('aria-valuemax')]);
        $isi = $xpath->query('./div', $progres)->item(0);
        $this->assertContains('bg-setor', explode(' ', $isi->getAttribute('class')));
        $this->assertSame('width: 50%', $isi->getAttribute('style'));
        $this->assertSame('1 dari 2 sudah setor', trim($xpath->query('.//*[@data-wajib-sudah]', $kotak)->item(0)->textContent));

        $tautan = $xpath->query('.//a[@data-tautan-wajib-belum]', $kotak)->item(0);
        $this->assertSame('#belum-setor-wajib', $tautan->getAttribute('href'));
        $this->assertSame('1 belum setor →', trim($tautan->textContent));

        // Rata-rata per anggota aktif di kotak sebelahnya.
        $rata = $xpath->query('.//*[@data-ringkasan-baris="rata_rata_aktif"]', $info)->item(0);
        $this->assertSame('Rata-rata per anggota aktif Rp 205.000', trim(preg_replace('/\s+/', ' ', $rata->textContent)));

        // Semua anggota aktif sudah setor: tautan disembunyikan.
        $citra = Anggota::where('nama', 'Citra Lestari')->first();
        $this->buatTransaksi($citra, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-11');
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame(0, $xpath->query('//*[@data-tautan-wajib-belum]')->length);
        $this->assertSame('2 dari 2 sudah setor', trim($xpath->query('//*[@data-wajib-sudah]')->item(0)->textContent));
    }

    public function test_tab_dan_segmen_garis_bawah_tetap_mengirim_nilai_yang_sama(): void
    {
        $halaman = [
            'dashboard' => [route('dashboard'), '//form[@data-filter-transaksi]'],
            'detail anggota' => [route('anggota.show', $this->budi), '//form[.//input[@name="jenis_transaksi"]]'],
            'riwayat' => [route('transaksi.riwayat'), '//form[@data-filter-riwayat]'],
        ];

        foreach ($halaman as $nama => [$url, $form]) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get($url.'?jenis_transaksi=setor')->getContent());
            $fieldset = $xpath->query($form.'//fieldset[@data-segmen-garis]')->item(0);
            $this->assertNotNull($fieldset, $nama);
            foreach (['flex', 'gap-5', 'border-b', 'border-stone-200'] as $kelas) {
                $this->assertContains($kelas, explode(' ', $fieldset->getAttribute('class')), "{$nama}: {$kelas}");
            }

            $radio = $xpath->query('.//input[@type="radio"][@name="jenis_transaksi"]', $fieldset);
            $this->assertSame(['', 'setor', 'tarik'], array_map(fn ($el) => $el->getAttribute('value'), iterator_to_array($radio)), $nama);
            $this->assertSame(['setor'], array_values(array_map(fn ($el) => $el->getAttribute('value'), array_filter(iterator_to_array($radio), fn ($el) => $el->hasAttribute('checked')))), $nama);

            foreach ($xpath->query('.//label/span', $fieldset) as $span) {
                $kelas = explode(' ', $span->getAttribute('class'));
                foreach (['-mb-px', 'border-b-2', 'border-transparent', 'pb-2', 'text-sm', 'text-stone-500', 'peer-checked:border-arang', 'peer-checked:font-medium', 'peer-checked:text-stone-900'] as $harus) {
                    $this->assertContains($harus, $kelas, "{$nama}: {$harus}");
                }
                $this->assertEmpty(array_filter($kelas, fn ($k) => str_contains($k, 'bg-') || str_contains($k, 'rounded')), "{$nama}: tanpa latar dan pil.");
            }
        }

        // Tab periode grafik: nilai dan aria-pressed tetap; gaya aktif dari aria-pressed, tanpa latar arang.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $grup = $xpath->query('//*[@role="group"][@aria-label="Periode"]')->item(0);
        $this->assertContains('border-b', explode(' ', $grup->getAttribute('class')));
        $tab = $xpath->query('./button[@data-periode]', $grup);
        $this->assertSame(['minggu', 'bulan', 'tiga_bulan', 'enam_bulan', 'tahun', 'lima_tahun'], array_map(fn ($el) => $el->getAttribute('data-periode'), iterator_to_array($tab)));
        foreach ($tab as $el) {
            $kelas = explode(' ', $el->getAttribute('class'));
            $this->assertContains('aria-pressed:border-arang', $kelas);
            $this->assertContains('border-b-2', $kelas);
            $this->assertNotContains('bg-arang', $kelas);
            $this->assertNotContains('rounded-full', $kelas);
        }
        $this->assertStringNotContainsString("classList.toggle('bg-arang'", file_get_contents(resource_path('js/grafik/arus-simpanan.js')));
    }

    public function test_pil_hanya_status_dan_transaksi_berbentuk_cap_dan_komponen_lama_tidak_dipakai(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('anggota.index'))->getContent());
        $status = $xpath->query('//main//*[@data-status-anggota="nonaktif"]')->item(0);
        foreach (['rounded', 'border', 'border-stone-300', 'bg-white', 'text-[13px]', 'font-medium'] as $kelas) {
            $this->assertContains($kelas, explode(' ', $status->getAttribute('class')), $kelas);
        }

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('anggota.show', $this->budi))->getContent());
        // Tabel detail anggota: pil transaksi seperti Riwayat, tanpa kotak ikon (R1 E.5).
        $this->assertSame(4, $xpath->query('//tbody//*[@data-pil-transaksi]')->length);
        $this->assertSame(0, $xpath->query('//tbody//*[local-name()="svg"]')->length);

        // Badge hitungan juga bersudut 4 px.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertContains('rounded', explode(' ', $xpath->query('//*[@data-jumlah-belum-setor]')->item(0)->getAttribute('class')));

        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($berkas as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $this->assertStringNotContainsString('<x-ui.jenis-transaksi', file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    private function buatTransaksi(Anggota $anggota, string $namaJenis, string $jenisTransaksi, int $jumlah, string $tanggal): TransaksiSimpanan
    {
        return TransaksiSimpanan::create([
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => JenisSimpanan::where('nama_jenis', $namaJenis)->value('id'),
            'staff_id' => $this->admin->id,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => 0,
        ]);
    }
}
