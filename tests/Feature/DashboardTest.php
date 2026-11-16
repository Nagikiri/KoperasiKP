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
        $response->assertSee('3 anggota baru bulan ini');
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
        $response->assertSee('Menampilkan 10 dari 12 anggota.');
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
        $response->assertSee('Lihat angka');
        $response->assertDontSee('Grafik muncul setelah transaksi pertama dicatat.');

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
        $response->assertSee('Grafik muncul setelah transaksi pertama dicatat.');
        $response->assertDontSee('id="grafik-arus"', false);
        $response->assertDontSee('id="data-grafik"', false);
        $response->assertDontSee('aria-label="Periode"', false);
        $response->assertDontSee('Lihat angka');

        $response->assertSee('Belum ada saldo tercatat.');
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
        $response->assertSee('Grafik muncul setelah transaksi pertama dicatat.');
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
            ->assertSee('2 transaksi tercatat hari ini.')
            ->assertDontSee('Belum ada transaksi hari ini.');

        // 1. Belum setor wajib didahulukan.
        Anggota::factory()->create();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('1 anggota belum setor wajib bulan ini.')
            ->assertDontSee('2 transaksi tercatat hari ini.');
    }

    public function test_data_komposisi_berisi_tiga_jenis_saat_saldo_lebih_dari_nol(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-11-02');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertSee('id="grafik-komposisi"', false);
        $response->assertDontSee('Belum ada saldo tercatat.');

        $xpath = $this->xpath($response->getContent());
        $data = json_decode($xpath->query('//script[@id="data-komposisi"]')->item(0)->textContent, true);

        $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA], array_column($data, 'nama_jenis'));
        $this->assertEquals([100000, 0, 300000], array_column($data, 'saldo'));

        // Persen 1 desimal di daftar.
        $daftar = $xpath->query('//*[@data-daftar-komposisi]')->item(0)->textContent;
        foreach (['25,0%', '0,0%', '75,0%', 'Rp 100.000', 'Rp 300.000'] as $teks) {
            $this->assertStringContainsString($teks, $daftar);
        }
        // Total di tengah donat.
        $response->assertSee('Rp 400.000');
        $response->assertSee('Total saldo');
    }

    public function test_kpi_anggota_baru_dan_bersih_bulan_ini_bertanda_sesuai_nilai(): void
    {
        $bersih = fn () => trim($this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent())
            ->query('//*[@data-bersih-bulan-ini]')->item(0)->textContent);

        // Nol: tanpa tanda.
        $this->assertSame('Rp 0', $bersih());

        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-03']);
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-11-05');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 30000, '2026-11-06');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 99000, '2026-10-30'); // bulan lalu

        $this->assertSame('+Rp 70.000', $bersih());

        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 110000, '2026-11-07');

        $this->assertSame('-Rp 40.000', $bersih());

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertSee('1 anggota baru bulan ini');
        $response->assertSee('Bersih bulan ini');
        $this->assertStringNotContainsString("\u{2212}", $response->getContent());
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
