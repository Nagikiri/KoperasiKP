<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_tampil_tanpa_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('ringkasanAnggota', ['aktif' => 0, 'nonaktif' => 0, 'baru_bulan_ini' => 0]);
        $response->assertViewHas('totalSaldo', 0.0);
        $response->assertViewHas('aktivitasHariIni', ['jumlah_transaksi' => 0, 'total_setor' => 0.0, 'total_tarik' => 0.0]);
        $response->assertViewHas('aktivitasBulanIni', ['jumlah_transaksi' => 0, 'total_setor' => 0.0, 'total_tarik' => 0.0]);
        $response->assertViewHas('jumlahBelumSetorWajib', 0);
        $this->assertCount(3, $response->viewData('rekapSaldo'));
        $this->assertCount(6, $response->viewData('trenBulanan'));
        $this->assertCount(0, $response->viewData('belumSetorWajib'));
        $this->assertCount(0, $response->viewData('transaksiTerbaru'));
    }

    public function test_ringkasan_anggota_aktif_nonaktif_dan_baru_bulan_ini(): void
    {
        Anggota::factory()->create(['tanggal_bergabung' => '2026-11-05']);
        Anggota::factory()->create(['tanggal_bergabung' => '2026-10-31']);
        Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        Anggota::factory()->nonaktif()->create(['tanggal_bergabung' => '2026-11-12']);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        // Baru bulan ini = tanggal_bergabung di bulan berjalan, aktif maupun nonaktif.
        $response->assertViewHas('ringkasanAnggota', ['aktif' => 3, 'nonaktif' => 1, 'baru_bulan_ini' => 3]);
        $this->assertSame([
            'Baru bulan ini' => ['3', 'text-stone-900'],
            'Nonaktif' => ['1', 'text-stone-900'],
        ], $this->rincianKartu($response->getContent(), 'anggota'));
    }

    public function test_aktivitas_hari_ini_dan_bulan_ini(): void
    {
        $anggota = Anggota::factory()->create();

        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-11-12');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 30000, '2026-11-12');
        $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-01');
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 70000, '2026-10-31');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertViewHas('aktivitasHariIni', ['jumlah_transaksi' => 2, 'total_setor' => 100000.0, 'total_tarik' => 30000.0]);
        $response->assertViewHas('aktivitasBulanIni', ['jumlah_transaksi' => 3, 'total_setor' => 150000.0, 'total_tarik' => 30000.0]);
    }

    public function test_tren_enam_bulan_urut_lama_ke_baru_termasuk_bulan_kosong(): void
    {
        $anggota = Anggota::factory()->create();

        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 99000, '2026-05-31'); // di luar 6 bulan
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 10000, '2026-06-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 20000, '2026-09-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 5000, '2026-09-30');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 7000, '2026-09-15');
        $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 30000, '2026-11-12');

        $tren = $this->actingAs($this->admin)->get(route('dashboard'))->viewData('trenBulanan');

        $this->assertSame([
            ['bulan' => 'Jun 2026', 'total_setor' => 10000.0, 'total_tarik' => 0.0],
            ['bulan' => 'Jul 2026', 'total_setor' => 0.0, 'total_tarik' => 0.0],
            ['bulan' => 'Agt 2026', 'total_setor' => 0.0, 'total_tarik' => 0.0],
            ['bulan' => 'Sep 2026', 'total_setor' => 25000.0, 'total_tarik' => 7000.0],
            ['bulan' => 'Okt 2026', 'total_setor' => 0.0, 'total_tarik' => 0.0],
            ['bulan' => 'Nov 2026', 'total_setor' => 30000.0, 'total_tarik' => 0.0],
        ], $tren->all());
    }

    public function test_belum_setor_wajib_bulan_ini(): void
    {
        $sudah = Anggota::factory()->create(['nama' => 'Sudah Wajib']);
        $bulanLalu = Anggota::factory()->create(['nama' => 'Wajib Bulan Lalu']);
        $hanyaPokok = Anggota::factory()->create(['nama' => 'Hanya Pokok']);
        $nonaktif = Anggota::factory()->nonaktif()->create(['nama' => 'Nonaktif']);

        $this->buatTransaksi($sudah, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-02');
        $this->buatTransaksi($bulanLalu, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-10-20');
        $this->buatTransaksi($hanyaPokok, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-03');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $daftar = $response->viewData('belumSetorWajib');

        $this->assertSame(['Hanya Pokok', 'Wajib Bulan Lalu'], $daftar->pluck('nama')->all());
        $this->assertFalse($daftar->contains('id', $sudah->id));
        $this->assertFalse($daftar->contains('id', $nonaktif->id));
        $response->assertViewHas('jumlahBelumSetorWajib', 2);
        $response->assertSee(route('transaksi.setor.create', ['anggota_id' => $hanyaPokok->id]), false);
    }

    public function test_belum_setor_wajib_maksimal_sepuluh(): void
    {
        Anggota::factory()->count(12)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $this->assertCount(10, $response->viewData('belumSetorWajib'));
        $response->assertViewHas('jumlahBelumSetorWajib', 12);

        // F2c E: sisa ditampilkan sebagai tautan ke form setor (tanpa anggota).
        $tautan = $this->xpath($response->getContent())->query('//a[@data-lihat-lainnya]')->item(0);
        $this->assertSame('Lihat 2 lainnya', trim($tautan->textContent));
        $this->assertSame(route('transaksi.setor.create'), $tautan->getAttribute('href'));
        $response->assertDontSee('Menampilkan 10 dari 12 anggota.');
    }

    public function test_kartu_belum_setor_wajib_judul_bulan_badge_dan_kaki(): void
    {
        Anggota::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());
        $kartu = $xpath->query('//*[@data-kartu-tindak-lanjut]')->item(0);

        $this->assertSame('Belum setor wajib', trim($xpath->query('.//h2', $kartu)->item(0)->textContent));
        $this->assertSame('Bulan November', trim($xpath->query('.//*[@data-bulan-berjalan]', $kartu)->item(0)->textContent));
        $badge = $xpath->query('.//*[@data-jumlah-belum-setor]', $kartu)->item(0);
        $this->assertSame('2 anggota', trim($badge->textContent));
        $this->assertStringContainsString('bg-amber-50', $badge->getAttribute('class'));
        $this->assertStringContainsString('Daftar ini hanya pengingat.', $kartu->textContent);
        $this->assertSame(0, $xpath->query('.//a[@data-lihat-lainnya]', $kartu)->length);

        foreach (['Perlu ditindaklanjuti', 'Belum setor wajib bulan ini</h3>', 'Informasi saja.'] as $lama) {
            $response->assertDontSee($lama, false);
        }
    }

    public function test_kartu_belum_setor_wajib_kosong_dengan_badge_nol(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $kartu = $xpath->query('//*[@data-kartu-tindak-lanjut]')->item(0);

        $this->assertSame('0 anggota', trim($xpath->query('.//*[@data-jumlah-belum-setor]', $kartu)->item(0)->textContent));
        $this->assertStringContainsString('Semua anggota aktif sudah setor wajib bulan ini.', $kartu->textContent);
        $this->assertSame(1, $xpath->query('.//svg[contains(@class,"text-setor")]', $kartu)->length);
    }

    public function test_aksi_cepat_tampil(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $pembungkus = $xpath->query('//*[@data-aksi-cepat]');
        $this->assertSame(1, $pembungkus->length, 'Pembungkus [data-aksi-cepat] harus ada tepat satu.');

        $href = [];
        foreach ($xpath->query('.//a', $pembungkus->item(0)) as $tautan) {
            $href[] = $tautan->getAttribute('href');
        }

        // F2b D.1: aksi cepat berada di kepala halaman (tambah anggota, catat penarikan, catat setoran).
        foreach (['anggota.create', 'transaksi.tarik.create', 'transaksi.setor.create'] as $route) {
            $this->assertContains(route($route), $href, "Tautan {$route} harus berada di dalam aksi cepat.");
        }

        // Tautan rekap pindah ke kaki kartu grafik.
        $rekap = $xpath->query('//a[@data-tautan-rekap]');
        $this->assertSame(1, $rekap->length);
        $this->assertSame(route('laporan.keseluruhan'), $rekap->item(0)->getAttribute('href'));
    }

    public function test_grafik_arus_dan_data_json_enam_periode_tampil_saat_ada_transaksi(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 25000, '2026-09-10');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertSee('id="grafik-arus"', false);
        $response->assertSee('<script type="application/json" id="data-grafik">', false);
        $response->assertSee('role="group" aria-label="Periode"', false);
        $response->assertSee('Tampilkan tabel');
        $response->assertDontSee('Grafik akan terisi setelah ada setoran atau penarikan.');

        $xpath = $this->xpath($response->getContent());
        $data = json_decode($xpath->query('//script[@id="data-grafik"]')->item(0)->textContent, true);

        $this->assertSame('enam_bulan', $data['default']);
        $this->assertTrue($data['ada_data']);
        $this->assertSame(['minggu', 'bulan', 'tiga_bulan', 'enam_bulan', 'tahun', 'lima_tahun'], array_keys($data['periode']));
        $this->assertSame(['Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov'], $data['periode']['enam_bulan']['label']);
        $this->assertEquals(25000, $data['periode']['enam_bulan']['setor'][3]);
        // JSON mengubah 0.0 menjadi 0, jadi dibandingkan longgar terhadap kontrak view.
        $this->assertEquals($response->viewData('grafikArus'), $data);

        // Tab periode: enam_bulan aktif secara bawaan.
        $this->assertSame('true', $xpath->query('//button[@data-periode="enam_bulan"]')->item(0)->getAttribute('aria-pressed'));
        $this->assertSame(6, $xpath->query('//*[@role="group"][@aria-label="Periode"]/button[@aria-pressed]')->length);
        $this->assertSame(4, $xpath->query('//*[@data-tampilan]')->length);
    }

    public function test_keadaan_kosong_grafik_dan_transaksi_terbaru_tanpa_elemen_grafik(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Belum ada transaksi');
        $response->assertSee('Grafik akan terisi setelah ada setoran atau penarikan.');
        $response->assertDontSee('id="grafik-arus"', false);
        $response->assertDontSee('id="data-grafik"', false);
        $response->assertDontSee('aria-label="Periode"', false);
        $response->assertDontSee('Tampilkan tabel');

        // Kaki grafik tetap ada, hanya berisi tautan laporan (F2c I.6).
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-kaki-grafik]//a[@data-tautan-rekap]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-tombol-tabel]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-baris-kontrol]')->length);

        $response->assertSee('Belum ada simpanan.');
        $response->assertDontSee('id="grafik-komposisi"', false);
        $response->assertDontSee('id="data-komposisi"', false);
    }

    public function test_grafik_tidak_dirender_jika_transaksi_lebih_tua_dari_lima_tahun(): void
    {
        $anggota = Anggota::factory()->create();
        // Rentang lima_tahun untuk 12 November 2026 dimulai 1 Januari 2022.
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2021-12-31');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $this->assertFalse($response->viewData('grafikArus')['ada_data']);
        $response->assertSee('Grafik akan terisi setelah ada setoran atau penarikan.');
        $response->assertDontSee('id="grafik-arus"', false);
        $response->assertDontSee('id="data-grafik"', false);
        // Saldo > 0: donat komposisi tetap dirender.
        $response->assertSee('id="grafik-komposisi"', false);
        $response->assertSee('<script type="application/json" id="data-komposisi">', false);
        // Transaksi terbaru tetap tampil walau grafik kosong.
        $response->assertSee('+Rp 100.000');
    }

    public function test_sapaan_mengikuti_jam_dan_kalimat_status_berurutan(): void
    {
        $this->admin->update(['nama' => 'Admin Koperasi']);

        // 3. Tanpa transaksi hari ini dan tanpa anggota yang belum setor wajib.
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('<h1 class="text-[26px] font-semibold tracking-tight text-stone-900">Selamat pagi, Admin Koperasi</h1>', false)
            ->assertSee('Belum ada transaksi hari ini.');

        Carbon::setTestNow('2026-11-12 19:30:00');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('Selamat malam, Admin Koperasi');
        Carbon::setTestNow('2026-11-12 10:00:00');

        // 2. Ada transaksi hari ini, semua anggota aktif sudah setor wajib.
        $sudah = Anggota::factory()->create();
        $this->buatTransaksi($sudah, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-12');
        $this->buatTransaksi($sudah, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 20000, '2026-11-12');

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('Hari ini sudah ada 2 transaksi.')
            ->assertDontSee('Belum ada transaksi hari ini.');

        // 1. Belum setor wajib didahulukan.
        Anggota::factory()->create();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('Ada 1 anggota yang belum setor wajib bulan ini.')
            ->assertDontSee('Hari ini sudah ada 2 transaksi.');
    }

    public function test_data_komposisi_berisi_tiga_jenis_saat_saldo_lebih_dari_nol(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-11-02');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertSee('id="grafik-komposisi"', false);
        $response->assertSee('Sebaran simpanan');
        $response->assertDontSee('Komposisi saldo');
        $response->assertDontSee('Per jenis simpanan');
        $response->assertDontSee('Belum ada simpanan.');

        $xpath = $this->xpath($response->getContent());
        $data = json_decode($xpath->query('//script[@id="data-komposisi"]')->item(0)->textContent, true);

        $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA], array_column($data, 'nama_jenis'));
        $this->assertEquals([100000, 0, 300000], array_column($data, 'saldo'));

        // Persen 1 desimal di daftar; nama jenis tanpa kata "Simpanan" (F2c C.4).
        $daftar = $xpath->query('//*[@data-daftar-komposisi]')->item(0)->textContent;
        foreach (['Pokok', 'Wajib', 'Sukarela', '25,0%', '0,0%', '75,0%', 'Rp 100.000', 'Rp 300.000'] as $teks) {
            $this->assertStringContainsString($teks, $daftar);
        }
        $this->assertStringNotContainsString('Simpanan', $daftar);
        // Total di tengah donat.
        $kartu = $xpath->query('//*[@data-kartu-komposisi]')->item(0)->textContent;
        $this->assertStringContainsString('Rp 400.000', $kartu);
        $this->assertStringContainsString('Total simpanan', $kartu);
        $this->assertStringNotContainsString('Total saldo', $kartu);
    }

    public function test_kepala_grafik_kontrol_satu_baris_chip_dan_kaki_grafik(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-08-10');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 100000, '2026-10-10');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());

        // Kepala: label tanpa nama periode, chip "dalam 6 bulan" (periode default).
        // Teks kartu tanpa isi <script> JSON (yang memang memuat nama periode).
        $teksKartu = '';
        foreach ($xpath->query('//*[@data-kartu-arus]//text()[not(ancestor::script)]') as $teks) {
            $teksKartu .= ' '.$teks->textContent;
        }
        $teksKartu = preg_replace('/\s+/', ' ', $teksKartu);
        $this->assertStringContainsString('Saldo simpanan', $teksKartu);
        $this->assertStringNotContainsString('6 bulan terakhir', $teksKartu);
        $this->assertSame('+Rp 400.000 dalam 6 bulan', trim($xpath->query('//*[@data-arus-chip]')->item(0)->textContent));
        $this->assertStringContainsString('Setoran Rp 500.000', $teksKartu);
        $this->assertStringContainsString('Penarikan Rp 100.000', $teksKartu);

        // Baris kontrol: tab periode dan pemilih tampilan dalam satu baris.
        $baris = $xpath->query('//*[@data-baris-kontrol]');
        $this->assertSame(1, $baris->length);
        $this->assertSame(1, $xpath->query('.//*[@role="group"][@aria-label="Periode"]', $baris->item(0))->length);
        $this->assertSame(1, $xpath->query('.//*[@data-tampilan-label]', $baris->item(0))->length);
        $this->assertSame('Garis', trim($xpath->query('//*[@data-tampilan-label]')->item(0)->textContent));
        $this->assertStringNotContainsString('Tampilan:', $baris->item(0)->textContent);
        $pilihan = [];
        foreach ($xpath->query('//*[@data-tampilan]') as $node) {
            $pilihan[$node->getAttribute('data-tampilan')] = trim($node->textContent);
        }
        $this->assertSame(['garis' => 'Garis', 'batang' => 'Batang', 'saldo' => 'Saldo', 'gabungan' => 'Gabungan'], $pilihan);
        $response->assertDontSee('Kombinasi');

        // Kaki: tombol tabel dan tautan laporan.
        $kaki = $xpath->query('//*[@data-kaki-grafik]');
        $this->assertSame(1, $kaki->length);
        $tombol = $xpath->query('.//button[@data-tombol-tabel]', $kaki->item(0))->item(0);
        $this->assertNotNull($tombol);
        $this->assertSame('false', $tombol->getAttribute('aria-expanded'));
        $this->assertStringContainsString('Tampilkan tabel', $tombol->textContent);
        $tautan = $xpath->query('.//a[@data-tautan-rekap]', $kaki->item(0))->item(0);
        $this->assertSame(route('laporan.keseluruhan'), $tautan->getAttribute('href'));
        $this->assertSame('Laporan lengkap', trim($tautan->textContent));
        $response->assertDontSee('Lihat rekap lengkap');

        // Tabel: kolom pertama "Bulan" untuk enam_bulan, baris terbaru di atas.
        $this->assertSame('Bulan', trim($xpath->query('//*[@data-arus-kolom-pertama]')->item(0)->textContent));
        $kepala = [];
        foreach ($xpath->query('//*[@id="tabel-arus"]//thead//th') as $th) {
            $kepala[] = trim($th->textContent);
        }
        $this->assertSame(['Bulan', 'Setoran', 'Penarikan', 'Saldo akhir'], $kepala);
        $baris = $xpath->query('//*[@data-arus-tabel]/tr');
        $this->assertSame(6, $baris->length);
        $this->assertStringContainsString('November 2026', $baris->item(0)->textContent);
        $this->assertStringContainsString('Juni 2026', $baris->item(5)->textContent);
    }

    public function test_chip_grafik_tidak_berubah_saat_bersih_nol(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        // Hanya transaksi di luar 6 bulan: bersih periode default = 0.
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-02-10');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $this->assertSame('Tidak berubah', trim($xpath->query('//*[@data-arus-chip]')->item(0)->textContent));
    }

    public function test_kpi_bersih_bulan_ini_dan_hari_ini_bertanda_dan_berwarna_sesuai_nilai(): void
    {
        $rincian = fn () => $this->rincianKartu($this->actingAs($this->admin)->get(route('dashboard'))->getContent(), 'total');

        // Nol: tanpa tanda, stone-900.
        $this->assertSame([
            'Bersih bulan ini' => ['Rp 0', 'text-stone-900'],
            'Bersih hari ini' => ['Rp 0', 'text-stone-900'],
        ], $rincian());

        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-03']);
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-11-05');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 30000, '2026-11-06');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 99000, '2026-10-30'); // bulan lalu
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 20000, '2026-11-12'); // hari ini

        $this->assertSame([
            'Bersih bulan ini' => ['+Rp 90.000', 'text-setor'],
            'Bersih hari ini' => ['+Rp 20.000', 'text-setor'],
        ], $rincian());

        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 150000, '2026-11-12');

        $this->assertSame([
            'Bersih bulan ini' => ['-Rp 60.000', 'text-tarik'],
            'Bersih hari ini' => ['-Rp 130.000', 'text-tarik'],
        ], $rincian());

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString("\u{2212}", $html);
    }

    public function test_empat_kartu_ringkasan_putih_bergaris_putus_dengan_dua_baris_rincian(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 250000, '2026-11-03');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $html = $response->getContent();
        $xpath = $this->xpath($html);

        $harapan = [
            'total' => ['Total simpanan', 'Rp 350.000', ['Bersih bulan ini', 'Bersih hari ini']],
            'anggota' => ['Anggota aktif', '1', ['Baru bulan ini', 'Nonaktif']],
            'setor' => ['Setoran bulan ini', 'Rp 350.000', ['Hari ini', 'Bulan lalu']],
            'tarik' => ['Penarikan bulan ini', 'Rp 0', ['Hari ini', 'Bulan lalu']],
        ];

        $kartu = $xpath->query('//*[@data-kartu-ringkasan]');
        $this->assertSame(4, $kartu->length);

        foreach ($harapan as $jangkar => [$judul, $angka, $label]) {
            $node = $xpath->query("//*[@data-kartu-ringkasan=\"{$jangkar}\"]")->item(0);
            $this->assertNotNull($node, "Kartu {$jangkar} harus ada.");

            $kelas = $node->getAttribute('class');
            $this->assertMatchesRegularExpression('/\bbg-white\b/', $kelas);
            $this->assertDoesNotMatchRegularExpression('/\bbg-arang\b/', $kelas, "Kartu {$jangkar} tidak boleh berlatar arang.");
            $this->assertStringNotContainsString('data-aksen', $node->ownerDocument->saveHTML($node));

            $teks = preg_replace('/\s+/', ' ', $node->textContent);
            $this->assertStringContainsString($judul, $teks);
            $this->assertStringContainsString($angka, $teks);

            // Garis putus-putus, lalu tepat dua baris rincian.
            $garis = $xpath->query('.//*[@data-garis-putus]', $node);
            $this->assertSame(1, $garis->length);
            $this->assertStringContainsString('border-dashed', $garis->item(0)->getAttribute('class'));
            $this->assertSame($label, array_keys($this->rincianKartu($html, $jangkar)));

            // Tanpa ikon, sparkline, badge, atau panah.
            $this->assertSame(0, $xpath->query('.//svg', $node)->length, "Kartu {$jangkar} tidak boleh memuat SVG.");
            $this->assertStringNotContainsString('rounded-full', $node->ownerDocument->saveHTML($node));
        }

        $this->assertStringNotContainsString('data-sparkline', $html);
        $this->assertStringNotContainsString('data-rincian-total', $html);
        $kartuGrid = $xpath->query('//*[@data-kartu-ringkasan]/..')->item(0)->getAttribute('class');
        $this->assertStringContainsString('xl:grid-cols-4', $kartuGrid);
        // Rincian per jenis tidak lagi di kartu Total (sudah ada di Sebaran simpanan).
        $this->assertStringNotContainsString('Pokok', $xpath->query('//*[@data-kartu-ringkasan="total"]')->item(0)->textContent);
        foreach (['▲', '▼', 'Total saldo simpanan', 'Setor bulan ini', 'Tarik bulan ini'] as $teks) {
            $response->assertDontSee($teks);
        }
    }

    public function test_rincian_setoran_dan_penarikan_hari_ini_dan_bulan_lalu_dari_data_grafik(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-10-05');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 25000, '2026-10-30');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 12000, '2026-11-12');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 50000, '2026-10-20');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $html = $response->getContent();
        $enamBulan = $response->viewData('grafikArus')['periode']['enam_bulan'];

        $this->assertEquals(125000, $enamBulan['setor'][4]);
        $this->assertSame([
            'Hari ini' => ['Rp 12.000', 'text-stone-900'],
            'Bulan lalu' => ['Rp 125.000', 'text-stone-900'],
        ], $this->rincianKartu($html, 'setor'));
        $this->assertSame([
            'Hari ini' => ['Rp 0', 'text-stone-900'],
            'Bulan lalu' => ['Rp 50.000', 'text-stone-900'],
        ], $this->rincianKartu($html, 'tarik'));
    }

    public function test_rincian_bulan_lalu_rp_nol_tanpa_data(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        foreach (['setor', 'tarik'] as $jangkar) {
            $this->assertSame([
                'Hari ini' => ['Rp 0', 'text-stone-900'],
                'Bulan lalu' => ['Rp 0', 'text-stone-900'],
            ], $this->rincianKartu($html, $jangkar));
        }
        $this->assertSame([
            'Baru bulan ini' => ['0', 'text-stone-900'],
            'Nonaktif' => ['0', 'text-stone-900'],
        ], $this->rincianKartu($html, 'anggota'));
    }

    public function test_transaksi_terbaru_menampilkan_ikon_label_tanda_dan_nama_staff(): void
    {
        $this->admin->update(['nama' => 'Siti Petugas']);
        $anggota = Anggota::factory()->create(['nama' => 'Budi Santoso', 'no_anggota' => 'KSP-2026-0007']);
        $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-10');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 15000, '2026-11-11');

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();
        $xpath = $this->xpath($html);

        $setor = $xpath->query('//tr[@data-transaksi="setor"]');
        $tarik = $xpath->query('//tr[@data-transaksi="tarik"]');
        $this->assertSame(1, $setor->length);
        $this->assertSame(1, $tarik->length);

        $barisSetor = $setor->item(0)->ownerDocument->saveHTML($setor->item(0));
        $barisTarik = $tarik->item(0)->ownerDocument->saveHTML($tarik->item(0));

        $this->assertStringContainsString('bg-setor-50', $barisSetor);
        $this->assertStringContainsString('<svg', $barisSetor);
        $this->assertMatchesRegularExpression('/>\s*Setor\s*</', $barisSetor);
        $this->assertStringContainsString(JenisSimpanan::WAJIB, $barisSetor);
        $this->assertStringContainsString('+Rp 50.000', $barisSetor);

        $this->assertStringContainsString('bg-tarik-50', $barisTarik);
        $this->assertMatchesRegularExpression('/>\s*Tarik\s*</', $barisTarik);
        $this->assertStringContainsString('-Rp 15.000', $barisTarik);

        foreach ([$barisSetor, $barisTarik] as $baris) {
            $this->assertStringContainsString('Budi Santoso', $baris);
            $this->assertStringContainsString('KSP-2026-0007', $baris);
            $this->assertStringContainsString('oleh Siti Petugas', $baris);
        }
    }

    public function test_tautan_catat_belum_setor_wajib_ke_form_setor_dengan_anggota_benar(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah']);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $catat = [];
        foreach ($xpath->query('//*[@data-belum-setor-wajib]//li') as $baris) {
            $tautan = $xpath->query('.//a[normalize-space()="Catat"]', $baris)->item(0);
            $this->assertNotNull($tautan, 'Setiap baris belum setor wajib punya tautan "Catat".');
            $catat[trim($xpath->query('.//span[contains(@class,"font-medium")]', $baris)->item(0)->textContent)] = $tautan->getAttribute('href');
        }

        $this->assertSame([
            'Budi Santoso' => route('transaksi.setor.create', ['anggota_id' => $budi->id]),
            'Siti Aminah' => route('transaksi.setor.create', ['anggota_id' => $siti->id]),
        ], $catat);
    }

    public function test_nominal_transaksi_terbaru_bertanda_ascii(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 150000, '2026-11-10');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 40000, '2026-11-11');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertSee('+Rp 150.000');
        $response->assertSee('-Rp 40.000');
        $this->assertStringNotContainsString("\u{2212}", $response->getContent());
    }

    public function test_dashboard_tanpa_n_plus_satu(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-01');
        $sebelum = $hitung();

        foreach (Anggota::factory()->count(5)->create() as $lain) {
            $this->buatTransaksi($lain, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-10');
            $this->buatTransaksi($lain, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 10000, '2026-09-10');
        }

        $this->assertSame($sebelum, $hitung());
    }

    public function test_rekap_per_jenis_benar_termasuk_jenis_kosong_dan_anggota_nonaktif(): void
    {
        $aktif = Anggota::factory()->create();
        $nonaktif = Anggota::factory()->nonaktif()->create();

        $this->buatTransaksi($aktif, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-01-10');
        $this->buatTransaksi($aktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-01-11');
        $this->buatTransaksi($aktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 200000, '2026-01-12');
        $this->buatTransaksi($nonaktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-01-13');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        $rekap = $response->viewData('rekapSaldo')->keyBy('nama_jenis');

        $this->assertSame(
            [JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA],
            $response->viewData('rekapSaldo')->pluck('nama_jenis')->all()
        );

        $this->assertSame(100000.0, $rekap[JenisSimpanan::POKOK]['total_setor']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::POKOK]['total_tarik']);
        $this->assertSame(100000.0, $rekap[JenisSimpanan::POKOK]['saldo']);

        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_setor']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_tarik']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['saldo']);

        $this->assertSame(800000.0, $rekap[JenisSimpanan::SUKARELA]['total_setor']);
        $this->assertSame(200000.0, $rekap[JenisSimpanan::SUKARELA]['total_tarik']);
        $this->assertSame(600000.0, $rekap[JenisSimpanan::SUKARELA]['saldo']);

        $response->assertViewHas('totalSaldo', 700000.0);
    }

    public function test_transaksi_terbaru_maksimal_sepuluh_dan_urut_terbaru(): void
    {
        $anggota = Anggota::factory()->create();

        // 12 transaksi pada tanggal berbeda: 2026-01-01 s.d. 2026-01-12.
        for ($hari = 1; $hari <= 12; $hari++) {
            $this->buatTransaksi(
                $anggota,
                JenisSimpanan::SUKARELA,
                TransaksiSimpanan::SETOR,
                10000,
                sprintf('2026-01-%02d', $hari)
            );
        }

        // Dua transaksi di tanggal yang sama: yang id-nya lebih besar tampil lebih dulu.
        $lebihAwal = $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 20000, '2026-01-12');
        $lebihAkhir = $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 30000, '2026-01-12');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        $terbaru = $response->viewData('transaksiTerbaru');

        $this->assertCount(10, $terbaru);
        $this->assertSame($lebihAkhir->id, $terbaru[0]->id);
        $this->assertSame($lebihAwal->id, $terbaru[1]->id);

        $tanggal = $terbaru->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d'))->all();
        $this->assertSame(
            ['2026-01-12', '2026-01-12', '2026-01-12', '2026-01-11', '2026-01-10',
                '2026-01-09', '2026-01-08', '2026-01-07', '2026-01-06', '2026-01-05'],
            $tanggal
        );
    }

    /**
     * Baris rincian kartu ringkasan: label => [nilai, kelas warna nilai].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function rincianKartu(string $html, string $jangkar): array
    {
        $xpath = $this->xpath($html);
        $hasil = [];
        foreach ($xpath->query("//*[@data-kartu-ringkasan=\"{$jangkar}\"]//*[@data-rincian]") as $baris) {
            $nilai = $xpath->query('.//dd', $baris)->item(0);
            preg_match('/\btext-(setor|tarik|stone-900)\b/', $nilai->getAttribute('class'), $warna);
            $hasil[trim($xpath->query('.//dt', $baris)->item(0)->textContent)] = [trim($nilai->textContent), $warna[0] ?? ''];
        }

        return $hasil;
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    private function buatTransaksi(
        Anggota $anggota,
        string $namaJenis,
        string $jenisTransaksi,
        int $jumlah,
        string $tanggal
    ): TransaksiSimpanan {
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
