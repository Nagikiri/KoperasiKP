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

    public function test_belum_setor_wajib_maksimal_lima_puluh_dan_tombol_lihat_semua(): void
    {
        Anggota::factory()->count(52)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        // F2d F.4, H.2.3: controller mengambil maksimal 50 per bulan; jumlah tetap penuh.
        $this->assertCount(50, $response->viewData('belumSetorWajib'));
        $response->assertViewHas('jumlahBelumSetorWajib', 52);

        $xpath = $this->xpath($response->getContent());
        $daftar = $xpath->query('//*[@data-daftar-wajib="ini"]')->item(0);
        $this->assertSame(50, $xpath->query('.//*[@data-belum-setor-wajib]/li', $daftar)->length);
        // Baris setelah batas tampil hanya saat dibuka penuh.
        $this->assertSame(38, $xpath->query('.//li[@data-baris-lebih][@x-show="semua"]', $daftar)->length);

        $tombol = $xpath->query('.//button[@data-lihat-semua-wajib]', $daftar)->item(0);
        $this->assertSame('Lihat semua 52 anggota', trim(preg_replace('/\s+/', ' ', $tombol->textContent)));
        $this->assertStringContainsString('Tampilkan lebih sedikit', $tombol->ownerDocument->saveHTML($tombol));
        $this->assertSame('dan 2 lainnya', trim($xpath->query('.//*[@data-sisa-wajib]', $daftar)->item(0)->textContent));

        // Tautan lama ke form setor dihapus.
        $this->assertSame(0, $xpath->query('//a[@data-lihat-lainnya]')->length);
        $response->assertDontSee('Menampilkan 10 dari 12 anggota.');
    }

    public function test_belum_setor_wajib_tanpa_tombol_lihat_semua_jika_muat(): void
    {
        Anggota::factory()->count(3)->create();

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $this->assertSame(0, $xpath->query('//*[@data-daftar-wajib="ini"]//*[@data-lihat-semua-wajib]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-sisa-wajib]')->length);
    }

    public function test_belum_setor_wajib_bulan_lalu(): void
    {
        $sudahBulanLalu = Anggota::factory()->create(['nama' => 'Sudah Oktober', 'tanggal_bergabung' => '2026-01-01']);
        $belum = Anggota::factory()->create(['nama' => 'Belum Oktober', 'tanggal_bergabung' => '2026-01-01']);
        Anggota::factory()->create(['nama' => 'Gabung 31 Okt', 'tanggal_bergabung' => '2026-10-31']);
        Anggota::factory()->create(['nama' => 'Gabung November', 'tanggal_bergabung' => '2026-11-01']);
        Anggota::factory()->nonaktif()->create(['nama' => 'Nonaktif', 'tanggal_bergabung' => '2026-01-01']);
        $hanyaSukarela = Anggota::factory()->create(['nama' => 'Hanya Sukarela', 'tanggal_bergabung' => '2026-01-01']);

        $this->buatTransaksi($sudahBulanLalu, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-10-31');
        $this->buatTransaksi($belum, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-09-30'); // di luar Oktober
        $this->buatTransaksi($belum, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-01'); // di luar Oktober
        $this->buatTransaksi($hanyaSukarela, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 50000, '2026-10-10');

        // F2d I.4: bulan lalu dipilih lewat query parameter `wajib=lalu`.
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => 'lalu']));
        $wajib = $response->viewData('wajib');

        // Yang bergabung bulan ini dan yang sudah setor wajib bulan lalu TIDAK masuk; nonaktif tidak masuk.
        $this->assertSame('lalu', $wajib['pilihan']);
        $this->assertSame('Bulan lalu', $wajib['label']);
        $this->assertSame(['Belum Oktober', 'Gabung 31 Okt', 'Hanya Sukarela'], $wajib['daftar']->pluck('nama')->all());
        $this->assertSame(3, $wajib['jumlah']);
        // Daftar bulan ini (kontrak lama) tetap dikirim, tanpa syarat tanggal bergabung.
        $this->assertSame(['Gabung 31 Okt', 'Gabung November', 'Hanya Sukarela', 'Sudah Oktober'], $response->viewData('belumSetorWajib')->pluck('nama')->all());

        $xpath = $this->xpath($response->getContent());
        $nama = [];
        foreach ($xpath->query('//*[@data-daftar-wajib="lalu"]//*[@data-belum-setor-wajib]/li//a[@data-tautan-detail]') as $node) {
            $nama[] = trim($node->textContent);
        }
        $this->assertSame(['Belum Oktober', 'Gabung 31 Okt', 'Hanya Sukarela'], $nama);
        $this->assertSame('3 anggota', $this->teksDi($xpath, '//*[@data-jumlah-belum-setor]'));
        $this->assertSame('Bulan lalu', $this->teksDi($xpath, '//*[@data-wajib-label]'));
    }

    public function test_kartu_belum_setor_wajib_judul_bulan_badge_dan_kaki(): void
    {
        Anggota::factory()->count(2)->create();
        Anggota::factory()->create(['tanggal_bergabung' => '2026-11-05']);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());
        $kartu = $xpath->query('//*[@data-kartu-tindak-lanjut]')->item(0);

        $this->assertSame('belum-setor-wajib', $kartu->getAttribute('id'));
        $this->assertSame('Belum setor wajib', trim($xpath->query('.//h2', $kartu)->item(0)->textContent));
        // F2d I.4.1: dropdown menu menggantikan segmen; dikirim lewat `wajib` dengan anchor kartu.
        $this->assertSame(0, $xpath->query('.//*[@data-pilih-bulan-wajib]', $kartu)->length);
        $form = $xpath->query('.//form[@data-pilih-wajib]', $kartu)->item(0);
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('dashboard').'#belum-setor-wajib', $form->getAttribute('action'));
        $this->assertSame('', $xpath->query('.//input[@type="hidden"][@name="wajib"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame('Bulan ini', $this->teksDi($xpath, '//*[@data-wajib-label]'));
        $this->assertSame('menu', $xpath->query('.//button[@aria-haspopup]', $form)->item(0)->getAttribute('aria-haspopup'));

        $opsi = [];
        foreach ($xpath->query('.//*[@role="menu"]//button[@role="menuitemradio"]', $form) as $tombol) {
            $opsi[$tombol->getAttribute('data-wajib-opsi')] = [trim($tombol->textContent), $tombol->getAttribute('aria-checked')];
        }
        // Bulan ini, Bulan lalu, 12 bulan sebelum bulan lalu (Sep 2026 s.d. Okt 2025), lalu tiga tunggakan.
        $this->assertSame(['Bulan ini', 'true'], $opsi['']);
        $this->assertSame(['Bulan lalu', 'false'], $opsi['lalu']);
        $this->assertSame(['September 2026', 'false'], $opsi['2026-09']);
        $this->assertSame(['Oktober 2025', 'false'], $opsi['2025-10']);
        $this->assertArrayNotHasKey('2026-10', $opsi);
        $this->assertArrayNotHasKey('2025-09', $opsi);
        $this->assertSame(['Tunggakan 3 bulan', 'false'], $opsi['tunggakan_3']);
        $this->assertSame(['Tunggakan 6 bulan', 'false'], $opsi['tunggakan_6']);
        $this->assertSame(['Tunggakan 12 bulan', 'false'], $opsi['tunggakan_12']);
        $this->assertCount(17, $opsi);
        $this->assertSame('Pilih bulan…', $this->teksDi($xpath, '//*[@data-wajib-pilih-bulan]'));
        $this->assertSame(1, $xpath->query('.//*[@role="menu"]//*[@role="separator"]', $form)->length);

        $badge = $xpath->query('.//*[@data-jumlah-belum-setor]', $kartu)->item(0);
        $this->assertSame('3 anggota', trim($badge->textContent));
        $this->assertStringContainsString('bg-emas-50', $badge->getAttribute('class'));
        $this->assertStringNotContainsString('bg-amber-50', $badge->getAttribute('class'));

        // F2d F.4: kaki lama dihapus.
        $this->assertStringNotContainsString('Daftar ini hanya pengingat.', $kartu->textContent);
        $this->assertSame(0, $xpath->query('.//a[@data-lihat-lainnya]', $kartu)->length);

        foreach (['Perlu ditindaklanjuti', 'Belum setor wajib bulan ini</h3>', 'Informasi saja.'] as $lama) {
            $response->assertDontSee($lama, false);
        }
    }

    public function test_kartu_belum_setor_wajib_kosong_dengan_badge_nol(): void
    {
        $kosong = function (array $parameter): array {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard', $parameter))->getContent());
            $kartu = $xpath->query('//*[@data-kartu-tindak-lanjut]')->item(0);

            return [
                trim($xpath->query('.//*[@data-jumlah-belum-setor]', $kartu)->item(0)->textContent),
                trim(preg_replace('/\s+/', ' ', $xpath->query('.//*[@data-daftar-wajib]', $kartu)->item(0)->textContent)),
                $xpath->query('.//*[@data-daftar-wajib]//svg[contains(concat(" ", @class, " ")," text-setor-700 ")]', $kartu)->length,
            ];
        };

        // I.4.4: teks kosong menyesuaikan pilihan.
        $this->assertSame(['0 anggota', 'Semua anggota aktif sudah setor wajib bulan ini.', 1], $kosong([]));
        $this->assertSame(['0 anggota', 'Semua anggota aktif sudah setor wajib di Oktober 2026.', 1], $kosong(['wajib' => 'lalu']));
        $this->assertSame(['0 anggota', 'Semua anggota aktif sudah setor wajib di Maret 2026.', 1], $kosong(['wajib' => '2026-03']));
        $this->assertSame(['0 anggota', 'Tidak ada tunggakan dalam 6 bulan terakhir.', 1], $kosong(['wajib' => 'tunggakan_6']));
    }

    public function test_pilihan_wajib_bulan_tertentu_dan_nilai_tidak_valid_kembali_ke_bulan_ini(): void
    {
        $gabungLama = Anggota::factory()->create(['nama' => 'Gabung Lama', 'tanggal_bergabung' => '2025-01-01']);
        Anggota::factory()->create(['nama' => 'Gabung April', 'tanggal_bergabung' => '2026-04-01']); // setelah Maret
        $sudahMaret = Anggota::factory()->create(['nama' => 'Sudah Maret', 'tanggal_bergabung' => '2025-01-01']);
        $this->buatTransaksi($sudahMaret, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-03-15');
        $this->buatTransaksi($gabungLama, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-02-28'); // bukan Maret

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => '2026-03']));
        $wajib = $response->viewData('wajib');
        $this->assertSame(['2026-03', 'Maret 2026', 'bulan', 1], [$wajib['pilihan'], $wajib['label'], $wajib['mode'], $wajib['jumlah']]);
        $this->assertSame(['Gabung Lama'], $wajib['daftar']->pluck('nama')->all());
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Maret 2026', $this->teksDi($xpath, '//*[@data-wajib-label]'));
        $this->assertSame('true', $xpath->query('//button[@data-wajib-opsi="2026-03"]')->item(0)->getAttribute('aria-checked'));
        // Daftar bulan terbuka karena pilihannya ada di sana.
        $this->assertSame('true', $xpath->query('//*[@data-wajib-pilih-bulan]')->item(0)->getAttribute('aria-expanded'));

        // Nilai tidak valid (termasuk bulan di luar 12 pilihan) -> bulan ini.
        foreach (['2026-10', '2025-09', '2026-11', 'tunggakan_5', 'kemarin', '2026-3'] as $nilai) {
            $wajib = $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => $nilai]))->viewData('wajib');
            $this->assertSame(['', 'Bulan ini', 'bulan'], [$wajib['pilihan'], $wajib['label'], $wajib['mode']], "wajib={$nilai}");
            $this->assertSame(3, $wajib['jumlah'], "wajib={$nilai}");
        }
    }

    public function test_tunggakan_wajib_menghitung_bulan_bolong_sejak_bergabung_dan_urut(): void
    {
        $andi = Anggota::factory()->create(['nama' => 'Andi', 'tanggal_bergabung' => '2020-01-01']);
        Anggota::factory()->create(['nama' => 'Budi', 'tanggal_bergabung' => '2020-01-01']);
        $citra = Anggota::factory()->create(['nama' => 'Citra', 'tanggal_bergabung' => '2026-09-20']);
        Anggota::factory()->create(['nama' => 'Dewi', 'tanggal_bergabung' => '2026-11-01']); // bergabung setelah rentang
        $eko = Anggota::factory()->create(['nama' => 'Eko', 'tanggal_bergabung' => '2020-01-01']);
        Anggota::factory()->nonaktif()->create(['nama' => 'Fajar', 'tanggal_bergabung' => '2020-01-01']);

        $wajib = fn (Anggota $a, string $tanggal) => $this->buatTransaksi($a, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, $tanggal);
        $wajib($andi, '2026-08-10');
        $wajib($andi, '2026-10-10');
        $wajib($andi, '2026-11-10'); // bulan berjalan tidak dihitung
        $wajib($citra, '2026-09-25');
        $wajib($eko, '2026-08-01');
        $wajib($eko, '2026-09-01');
        $wajib($eko, '2026-10-31');
        $this->buatTransaksi($citra, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 50000, '2026-10-05'); // bukan wajib

        // Tunggakan 3 bulan = Agustus–Oktober 2026.
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => 'tunggakan_3']));
        $hasil = $response->viewData('wajib');
        $this->assertSame(['tunggakan_3', 'Tunggakan 3 bulan', 'tunggakan', 3], [$hasil['pilihan'], $hasil['label'], $hasil['mode'], $hasil['jumlah']]);
        $this->assertSame(['Budi' => 3, 'Andi' => 1, 'Citra' => 1], $hasil['daftar']->mapWithKeys(fn ($a) => [$a->nama => $a->bolong])->all());

        $xpath = $this->xpath($response->getContent());
        $baris = [];
        foreach ($xpath->query('//*[@data-daftar-wajib="tunggakan_3"]//*[@data-belum-setor-wajib]/li') as $li) {
            $baris[] = trim($xpath->query('.//a[@data-tautan-detail]', $li)->item(0)->textContent)
                .' | '.trim($xpath->query('.//*[@data-bolong]', $li)->item(0)->textContent);
        }
        $this->assertSame(['Budi | bolong 3 bulan', 'Andi | bolong 1 bulan', 'Citra | bolong 1 bulan'], $baris);
        $this->assertSame('3 anggota', $this->teksDi($xpath, '//*[@data-jumlah-belum-setor]'));
        $this->assertSame('Tunggakan 3 bulan', $this->teksDi($xpath, '//*[@data-wajib-label]'));

        // Tunggakan 6 bulan = Mei–Oktober 2026.
        $hasil = $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => 'tunggakan_6']))->viewData('wajib');
        $this->assertSame(['Budi' => 6, 'Andi' => 4, 'Eko' => 3, 'Citra' => 1], $hasil['daftar']->mapWithKeys(fn ($a) => [$a->nama => $a->bolong])->all());

        // Mode bulan tidak menampilkan teks bolong.
        $this->assertSame(0, $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent())->query('//*[@data-bolong]')->length);
    }

    public function test_tunggakan_wajib_tanpa_n_plus_satu(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('dashboard', ['wajib' => 'tunggakan_12']))->assertOk();
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $a = Anggota::factory()->create();
        $this->buatTransaksi($a, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-05-01');
        $sebelum = $hitung();

        foreach (Anggota::factory()->count(6)->create() as $lain) {
            $this->buatTransaksi($lain, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-06-01');
            $this->buatTransaksi($lain, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-07-01');
        }

        $this->assertSame($sebelum, $hitung());
    }

    public function test_form_wajib_dan_form_filter_transaksi_saling_membawa_parameter(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard', [
            'cari' => 'budi', 'jenis_transaksi' => 'setor', 'wajib' => 'tunggakan_3',
        ]))->getContent());

        $tersembunyi = function (string $form) use ($xpath): array {
            $hasil = [];
            foreach ($xpath->query("//form[@{$form}]//input[@type=\"hidden\"]") as $input) {
                $hasil[$input->getAttribute('name')] = $input->getAttribute('value');
            }

            return $hasil;
        };

        $this->assertSame(['cari' => 'budi', 'jenis_transaksi' => 'setor', 'wajib' => 'tunggakan_3'], $tersembunyi('data-pilih-wajib'));
        $this->assertSame('tunggakan_3', $tersembunyi('data-filter-transaksi')['wajib']);

        // Tanpa pilihan wajib, form filter tidak mengirim `wajib` kosong.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame(0, $xpath->query('//form[@data-filter-transaksi]//input[@name="wajib"]')->length);
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

        $response = $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertDontSee('Hari ini sudah ada 2 transaksi.');
        // F2d I.5: "{n} anggota" menjadi tautan ke kartu; R1 F.1: tanpa tambahan "… juga belum bulan lalu".
        $xpath = $this->xpath($response->getContent());
        $kalimat = $xpath->query('//h1/following-sibling::p')->item(0);
        $this->assertSame('Ada 1 anggota yang belum setor wajib bulan ini.', trim(preg_replace('/\s+/', ' ', $kalimat->textContent)));
        $tautan = $xpath->query('.//a[@data-tautan-belum-wajib]', $kalimat)->item(0);
        $this->assertSame('#belum-setor-wajib', $tautan->getAttribute('href'));
        $this->assertSame('1 anggota', trim($tautan->textContent));
    }

    public function test_kalimat_status_tetap_bulan_ini_tanpa_menyebut_bulan_lalu(): void
    {
        // R1 F.1: kalimat cukup "Ada {n anggota} yang belum setor wajib bulan ini."; tidak pernah menyebut bulan lalu.
        $lama = Anggota::factory()->count(3)->create(['tanggal_bergabung' => '2026-01-01']);
        Anggota::factory()->create(['tanggal_bergabung' => '2026-11-03']); // baru: belum bulan ini
        // Satu anggota lama sudah setor wajib bulan lalu; dua lainnya belum bulan ini maupun bulan lalu.
        $this->buatTransaksi($lama[0], JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-10-05');

        $kalimat = function (array $parameter = []): string {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard', $parameter))->getContent());

            return trim(preg_replace('/\s+/', ' ', $xpath->query('//h1/following-sibling::p')->item(0)->textContent));
        };

        $harapan = 'Ada 4 anggota yang belum setor wajib bulan ini.';
        $this->assertSame($harapan, $kalimat());
        // Pilihan di kartu tidak mengubah kalimat status (I.5.1).
        $this->assertSame($harapan, $kalimat(['wajib' => 'tunggakan_6']));
        $this->assertSame($harapan, $kalimat(['wajib' => 'lalu']));
        $this->assertStringNotContainsString('bulan lalu', $kalimat());

        // Satu anggota setor wajib bulan ini -> jumlah turun, kalimat tetap tanpa tambahan.
        $this->buatTransaksi($lama[1], JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-06');
        $this->assertSame('Ada 3 anggota yang belum setor wajib bulan ini.', $kalimat());
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
        // F2d D.1: tengah donat = jenis terbesar. R1 F.2.1: total di kepala kartu ("Total simpanan" + nominal text-2xl);
        // baris Total di bawah daftar jenis dihapus, dan daftar jenis tanpa garis antarbaris (F.2.3).
        $this->assertSame('75,0%', $this->teksDi($xpath, '//*[@data-komposisi-tengah]/*[@data-tengah-persen]'));
        $this->assertSame('Sukarela', $this->teksDi($xpath, '//*[@data-komposisi-tengah]/*[@data-tengah-nama]'));
        $this->assertSame('Total simpanan Rp 400.000', $this->teksDi($xpath, '//*[@data-komposisi-total]'));
        $nominalTotal = $xpath->query('//*[@data-komposisi-total]/p[2]')->item(0);
        foreach (['text-2xl', 'font-semibold', 'tabular-nums'] as $kelas) {
            $this->assertContains($kelas, explode(' ', $nominalTotal->getAttribute('class')), $kelas);
        }
        $this->assertSame(0, $xpath->query('//*[@data-kartu-komposisi]//*[contains(@class, "text-2xl")][not(ancestor-or-self::*[@data-komposisi-total])]')->length, 'Hanya Total yang paling besar.');
        $this->assertSame(0, $xpath->query('//*[@data-daftar-komposisi]/following-sibling::*[normalize-space(.)="Total"] | //*[@data-kartu-komposisi]//*[starts-with(normalize-space(.), "Total 100%")]')->length, 'Baris Total lama dihapus.');
        $this->assertStringNotContainsString('divide-y', $xpath->query('//*[@data-daftar-komposisi]')->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('Total saldo', $xpath->query('//*[@data-kartu-komposisi]')->item(0)->textContent);
    }

    public function test_ringkasan_sebaran_set_pertama_mengikuti_data_dan_kelompok_saldo_sama_dengan_total(): void
    {
        $a = Anggota::factory()->create(['nama' => 'A']);
        $b = Anggota::factory()->create(['nama' => 'B']);
        $c = Anggota::factory()->nonaktif()->create(['nama' => 'C']);
        $this->buatTransaksi($a, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-10-01');
        $this->buatTransaksi($a, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-05');
        $this->buatTransaksi($a, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-10-02');
        $this->buatTransaksi($a, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 50000, '2026-10-03');
        $this->buatTransaksi($b, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-10-04');
        $this->buatTransaksi($c, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 70000, '2026-01-05');
        $this->buatTransaksi($c, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 30000, '2026-01-06');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertViewHas('ringkasanSebaran', [
            'bisa_ditarik' => 350000.0,
            'tertahan' => 150000.0,
            'milik_nonaktif' => 100000.0,
            'rata_rata_aktif' => 250000.0,
            'wajib_masuk_bulan_ini' => 50000.0,
            'sudah_setor_wajib' => 1,
            'anggota_aktif' => 2,
        ]);

        $ringkasan = $response->viewData('ringkasanSebaran');
        $this->assertSame(
            $response->viewData('totalSaldo'),
            round($ringkasan['bisa_ditarik'] + $ringkasan['tertahan'] + $ringkasan['milik_nonaktif'], 2),
            'Kelompok saldo dijumlahkan harus sama dengan baris Total.'
        );

        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Total simpanan Rp 600.000', $this->teksDi($xpath, '//*[@data-komposisi-total]'));
        $this->assertSame([
            'bisa_ditarik' => 'Bisa ditarik Rp 350.000',
            'tertahan' => 'Tertahan (anggota aktif) Rp 150.000',
            'milik_nonaktif' => 'Milik anggota nonaktif Rp 100.000',
            'rata_rata_aktif' => 'Rata-rata per anggota aktif Rp 250.000',
            'wajib_masuk_bulan_ini' => 'Wajib masuk bulan ini Rp 50.000 1 dari 2 sudah setor 1 belum setor →',
        ], $this->barisRingkasanSebaran($xpath));

        // R1 F.2.4/F.2.6: dua kelompok berjudul kecil (Kondisi saldo, Informasi anggota), tanpa garis putus-putus.
        $this->assertSame(['saldo', 'anggota'], array_map(fn ($n) => $n->getAttribute('data-ringkasan-kelompok'), iterator_to_array($xpath->query('//*[@data-komposisi-ringkasan]/section'))));
        $this->assertSame('Kondisi saldo', $this->teksDi($xpath, '//*[@data-kondisi-saldo]/h3'));
        $this->assertSame('Informasi anggota', $this->teksDi($xpath, '//*[@data-info-anggota]/h3'));
        $this->assertSame(0, $xpath->query('//*[@data-komposisi-ringkasan]//*[contains(@class,"border-dashed")]')->length);
        $teks = $xpath->query('//*[@data-kartu-komposisi]')->item(0)->textContent;
        $this->assertStringNotContainsString('Terkecil', $teks);
        $this->assertStringNotContainsString('Selisih', $teks);
    }

    public function test_ringkasan_sebaran_set_kedua_mengikuti_data_tanpa_baris_nonaktif(): void
    {
        $d = Anggota::factory()->create(['nama' => 'D']);
        Anggota::factory()->create(['nama' => 'E']); // aktif tanpa transaksi
        $this->buatTransaksi($d, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 1000000, '2026-09-01');
        $this->buatTransaksi($d, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 20000, '2026-10-15'); // bulan lalu

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertViewHas('ringkasanSebaran', [
            'bisa_ditarik' => 1000000.0,
            'tertahan' => 20000.0,
            'milik_nonaktif' => 0.0,
            'rata_rata_aktif' => 510000.0,
            'wajib_masuk_bulan_ini' => 0.0,
            'sudah_setor_wajib' => 0,
            'anggota_aktif' => 2,
        ]);

        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Total simpanan Rp 1.020.000', $this->teksDi($xpath, '//*[@data-komposisi-total]'));
        $this->assertSame([
            'bisa_ditarik' => 'Bisa ditarik Rp 1.000.000',
            'tertahan' => 'Tertahan (anggota aktif) Rp 20.000',
            'rata_rata_aktif' => 'Rata-rata per anggota aktif Rp 510.000',
            'wajib_masuk_bulan_ini' => 'Wajib masuk bulan ini Rp 0 0 dari 2 sudah setor 2 belum setor →',
        ], $this->barisRingkasanSebaran($xpath));
    }

    public function test_ringkasan_sebaran_tanpa_anggota_aktif_menyembunyikan_rata_rata(): void
    {
        $c = Anggota::factory()->nonaktif()->create();
        $this->buatTransaksi($c, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 40000, '2026-03-01');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        // Tanpa anggota aktif: kotak rata-rata disembunyikan, tautan "belum setor" tidak ada (0).
        $this->assertSame([
            'bisa_ditarik' => 'Bisa ditarik Rp 0',
            'tertahan' => 'Tertahan (anggota aktif) Rp 0',
            'milik_nonaktif' => 'Milik anggota nonaktif Rp 40.000',
            'wajib_masuk_bulan_ini' => 'Wajib masuk bulan ini Rp 0 0 dari 0 sudah setor',
        ], $this->barisRingkasanSebaran($xpath));
        $this->assertSame(0, $xpath->query('//*[@data-tautan-wajib-belum]')->length);
    }

    public function test_sebaran_seri_memilih_id_lebih_kecil_dan_tanpa_ringkasan_saat_total_nol(): void
    {
        $anggota = Anggota::factory()->create();
        // Pokok dan Sukarela seri sebagai terbesar.
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-11-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-11-02');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame('Pokok', $this->teksDi($xpath, '//*[@data-tengah-nama]'));

        // Total 0: donat, tengah, dan ringkasan tidak dirender; kepala Total tetap Rp 0 (R1 F.2.1).
        TransaksiSimpanan::query()->delete();
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());
        $this->assertSame(0, $xpath->query('//*[@data-komposisi-tengah]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-komposisi-ringkasan]')->length);
        $this->assertSame('Total simpanan Rp 0', $this->teksDi($xpath, '//*[@data-komposisi-total]'));
    }

    public function test_kepala_grafik_kontrol_satu_baris_kotak_dan_kaki_grafik(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-08-10');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 100000, '2026-10-10');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());

        // Kepala: label tanpa nama periode. F2d C.1: chip dan legenda dihapus, diganti tiga kotak.
        // Teks kartu tanpa isi <script> JSON (yang memang memuat nama periode).
        $teksKartu = '';
        foreach ($xpath->query('//*[@data-kartu-arus]//text()[not(ancestor::script)]') as $teks) {
            $teksKartu .= ' '.$teks->textContent;
        }
        $teksKartu = preg_replace('/\s+/', ' ', $teksKartu);
        $this->assertStringContainsString('Saldo simpanan', $teksKartu);
        $this->assertStringNotContainsString('6 bulan terakhir', $teksKartu);
        $this->assertSame(0, $xpath->query('//*[@data-arus-chip]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-arus-legenda-saldo]')->length);
        $this->assertSame('Rp 500.000', $this->teksDi($xpath, '//*[@data-arus-kotak="setor"]//*[@data-arus-nilai]'));
        $this->assertSame('Rp 100.000', $this->teksDi($xpath, '//*[@data-arus-kotak="tarik"]//*[@data-arus-nilai]'));
        $this->assertSame('+Rp 400.000', $this->teksDi($xpath, '//*[@data-arus-kotak="selisih"]//*[@data-arus-nilai]'));

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

    public function test_kotak_selisih_rp_nol_tanpa_tanda_saat_bersih_nol(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        // Hanya transaksi di luar 6 bulan: bersih periode default = 0.
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-02-10');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $nilai = $xpath->query('//*[@data-arus-kotak="selisih"]//*[@data-arus-nilai]')->item(0);
        $this->assertSame('Rp 0', trim(preg_replace('/\s+/', ' ', $nilai->textContent)));
        $this->assertStringContainsString('text-stone-900', $nilai->getAttribute('class'));
        // Label kotak tidak berubah walau tanda berubah (C.3).
        $this->assertStringContainsString('Selisih bersih', $xpath->query('//*[@data-arus-kotak="selisih"]')->item(0)->textContent);
        $this->assertSame('0 transaksi', $this->teksDi($xpath, '//*[@data-arus-kotak="selisih"]//*[@data-arus-jumlah]'));
    }

    public function test_tiga_kotak_dan_baris_persamaan_periode_default_dengan_angka_benar(): void
    {
        Carbon::setTestNow('2026-11-12 14:30:00');
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2025-01-01']);
        // Sebelum enam_bulan (1 Jun): saldo awal 800.000.
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 1000000, '2026-03-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 200000, '2026-03-02');
        // Dalam enam_bulan.
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-07-01');
        $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 100000, '2026-08-01');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 150000, '2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());

        $this->assertSame('Diperbarui 12 Nov, 14.30', $this->teksDi($xpath, '//*[@data-arus-diperbarui]'));
        $this->assertSame('Rp 1.050.000', $this->teksDi($xpath, '//*[@data-arus-saldo]'));

        $kotak = fn (string $kunci, string $bagian) => $this->teksDi($xpath, "//*[@data-arus-kotak=\"{$kunci}\"]//*[@{$bagian}]");
        $this->assertSame(['setor', 'tarik', 'selisih'], array_map(fn ($n) => $n->getAttribute('data-arus-kotak'), iterator_to_array($xpath->query('//*[@data-arus-kotak]'))));
        $this->assertSame('6 bulan', $kotak('setor', 'data-arus-periode-teks'));

        // F2d I.1.2: isi kotak hanya label, nominal, jumlah transaksi (selisih = setor + tarik).
        $this->assertSame('Rp 400.000', $kotak('setor', 'data-arus-nilai'));
        $this->assertSame('2 transaksi', $kotak('setor', 'data-arus-jumlah'));
        $this->assertSame('Rp 150.000', $kotak('tarik', 'data-arus-nilai'));
        $this->assertSame('1 transaksi', $kotak('tarik', 'data-arus-jumlah'));
        $this->assertSame('+Rp 250.000', $kotak('selisih', 'data-arus-nilai'));
        $this->assertSame('3 transaksi', $kotak('selisih', 'data-arus-jumlah'));
        $this->assertStringContainsString('text-naik', $xpath->query('//*[@data-arus-kotak="selisih"]//*[@data-arus-nilai]')->item(0)->getAttribute('class'));

        // I.1.1: sparkline dan semua persen dihapus.
        foreach (['data-arus-spark', 'data-arus-banding', 'data-arus-persen'] as $atribut) {
            $this->assertSame(0, $xpath->query("//*[@{$atribut}]")->length, "{$atribut} harus dihapus.");
        }
        $teksKartu = $xpath->query('//*[@data-kartu-arus]')->item(0)->textContent;
        $this->assertStringNotContainsString('vs sebelumnya', $teksKartu);
        $this->assertStringNotContainsString('dari saldo awal', $teksKartu);

        // Baris persamaan: 800.000 + 400.000 - 150.000 = 1.050.000.
        $persamaan = [];
        foreach ($xpath->query('//*[@data-arus-persamaan]//*[@data-persamaan]') as $node) {
            $persamaan[$node->getAttribute('data-persamaan')] = trim($node->textContent);
        }
        $this->assertSame(['saldo_awal' => 'Rp 800.000', 'total_setor' => 'Rp 400.000', 'total_tarik' => 'Rp 150.000', 'saldo_akhir' => 'Rp 1.050.000'], $persamaan);
        // I.2: penanda Sesuai/Tidak sesuai dihapus.
        $this->assertSame(0, $xpath->query('//*[@data-arus-cek]')->length);
        $this->assertStringNotContainsString('Sesuai', $xpath->query('//*[@data-arus-persamaan]')->item(0)->textContent);
        // Operator ASCII; tanpa emoji centang.
        $this->assertStringNotContainsString("\u{2212}", $response->getContent());
        $this->assertStringNotContainsString("\u{2713}", $response->getContent());
    }

    public function test_nominal_kotak_tidak_terpotong_dan_hanya_turun_baris_setelah_rp(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2025-01-01']);
        // 13 digit di kotak Setoran dan Selisih.
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 1234567890123, '2026-11-02');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 1234567890, '2026-11-03');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $this->assertSame('Rp 1.234.567.890.123', $this->teksDi($xpath, '//*[@data-arus-kotak="setor"]//*[@data-arus-nilai]'));
        $this->assertSame('Rp 1.234.567.890', $this->teksDi($xpath, '//*[@data-arus-kotak="tarik"]//*[@data-arus-nilai]'));
        $this->assertSame('+Rp 1.233.333.322.233', $this->teksDi($xpath, '//*[@data-arus-kotak="selisih"]//*[@data-arus-nilai]'));

        foreach (['setor', 'tarik', 'selisih'] as $kunci) {
            $kotak = $xpath->query("//*[@data-arus-kotak=\"{$kunci}\"]")->item(0);
            $this->assertStringContainsString('[container-type:inline-size]', $kotak->getAttribute('class'));

            $nilai = $xpath->query('.//*[@data-arus-nilai]', $kotak)->item(0);
            $kelas = $nilai->getAttribute('class');
            $this->assertStringContainsString('text-[clamp(16px,9cqi,24px)]', $kelas);
            foreach (['truncate', 'text-ellipsis', 'overflow-hidden'] as $larangan) {
                $this->assertStringNotContainsString($larangan, $kelas, "Nominal {$kunci} tidak boleh {$larangan}.");
            }
            // Dua bagian tanpa pemutusan: "Rp" dan angka; satu-satunya titik putus ada di antaranya.
            $this->assertStringContainsString('whitespace-nowrap', $xpath->query('.//*[@data-nilai-rp]', $nilai)->item(0)->getAttribute('class'));
            $this->assertStringContainsString('whitespace-nowrap', $xpath->query('.//*[@data-nilai-angka]', $nilai)->item(0)->getAttribute('class'));
        }
    }

    public function test_tanpa_data_grafik_kotak_tetap_tampil_tanpa_persamaan(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $this->assertSame(3, $xpath->query('//*[@data-arus-kotak]')->length);
        $this->assertSame('Rp 0', $this->teksDi($xpath, '//*[@data-arus-kotak="setor"]//*[@data-arus-nilai]'));
        $this->assertSame('0 transaksi', $this->teksDi($xpath, '//*[@data-arus-kotak="setor"]//*[@data-arus-jumlah]'));
        $this->assertSame('0 transaksi', $this->teksDi($xpath, '//*[@data-arus-kotak="selisih"]//*[@data-arus-jumlah]'));
        $this->assertSame(0, $xpath->query('//*[@data-arus-persamaan]')->length);
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
            'Bersih bulan ini' => ['+Rp 90.000', 'text-naik'],
            'Bersih hari ini' => ['+Rp 20.000', 'text-naik'],
        ], $rincian());

        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 150000, '2026-11-12');

        $this->assertSame([
            'Bersih bulan ini' => ['-Rp 60.000', 'text-turun'],
            'Bersih hari ini' => ['-Rp 130.000', 'text-turun'],
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

    public function test_transaksi_terbaru_pil_transaksi_label_tanda_tanpa_nama_staff(): void
    {
        // R1 E.3: kolom Anggota | Tanggal | Transaksi (pil seperti Riwayat) | Jumlah; tanpa kotak ikon dan tanpa "oleh {staf}".
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

        $this->assertSame(['Anggota', 'Tanggal', 'Transaksi', 'Jumlah'], array_map(
            fn ($th) => trim($th->textContent),
            iterator_to_array($xpath->query('//tr[@data-transaksi]/ancestor::table/thead//th'))
        ));

        foreach (['setor' => [$setor, 'Setor', 'Wajib', 'bg-setor', '+Rp 50.000', 'text-setor-700'], 'tarik' => [$tarik, 'Tarik', 'Sukarela', 'bg-tarik', '-Rp 15.000', 'text-tarik-700']] as $jenis => [$tr, $label, $namaJenis, $titik, $nominal, $warna]) {
            $baris = $tr->item(0);
            $pil = $xpath->query('./td[3]//*[@data-pil-transaksi="'.$jenis.'"]', $baris);
            $this->assertSame(1, $pil->length, $jenis);
            $this->assertSame("{$label}· {$namaJenis}", trim(preg_replace('/\s+/', ' ', $pil->item(0)->textContent)));
            $this->assertContains($titik, explode(' ', $xpath->query('./span[@aria-hidden="true"]', $pil->item(0))->item(0)->getAttribute('class')));
            $this->assertSame(0, $xpath->query('.//*[local-name()="svg"]', $baris)->length, 'Tanpa kotak ikon.');

            $this->assertSame('Budi Santoso', trim($xpath->query('./td[1]//a[@data-tautan-detail]', $baris)->item(0)->textContent));
            $this->assertSame('KSP-2026-0007', trim($xpath->query('./td[1]//*[@data-baris-no-anggota]', $baris)->item(0)->textContent));
            $this->assertSame(0, $xpath->query('./td[1]//span[@aria-hidden="true"][contains(@class, "w-9")]', $baris)->length, 'Tanpa avatar.');

            $sel = $xpath->query('./td[4][@data-nominal]', $baris)->item(0);
            $this->assertSame($nominal, trim($sel->textContent));
            $this->assertContains($warna, explode(' ', $sel->getAttribute('class')));
        }

        $this->assertStringNotContainsString('Siti Petugas', $barisSetor.$barisTarik);
    }

    public function test_tautan_catat_belum_setor_wajib_ke_form_setor_dengan_anggota_benar(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah']);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent());

        $catat = [];
        foreach ($xpath->query('//*[@data-daftar-wajib="ini"]//*[@data-belum-setor-wajib]//li') as $baris) {
            $tautan = $xpath->query('.//a[normalize-space()="Catat"]', $baris)->item(0);
            $this->assertNotNull($tautan, 'Setiap baris belum setor wajib punya tautan "Catat".');
            $catat[trim($xpath->query('.//a[@data-tautan-detail]', $baris)->item(0)->textContent)] = $tautan->getAttribute('href');
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
            preg_match('/\btext-(setor|tarik|naik|turun|stone-900)\b/', $nilai->getAttribute('class'), $warna);
            $hasil[trim($xpath->query('.//dt', $baris)->item(0)->textContent)] = [trim($nilai->textContent), $warna[0] ?? ''];
        }

        return $hasil;
    }

    /**
     * Baris ringkasan sebaran: kunci data-ringkasan-baris => teks (spasi dirapikan).
     *
     * @return array<string, string>
     */
    private function barisRingkasanSebaran(\DOMXPath $xpath): array
    {
        $hasil = [];
        foreach ($xpath->query('//*[@data-komposisi-ringkasan]//*[@data-ringkasan-baris]') as $baris) {
            // R1 F.2.5: isi popover ⓘ (role="tooltip") tidak ikut teks baris; diuji terpisah.
            $teks = implode(' ', array_map(fn ($node) => $node->nodeValue, iterator_to_array($xpath->query('.//text()[not(ancestor::*[@role="tooltip"])]', $baris))));
            $hasil[$baris->getAttribute('data-ringkasan-baris')] = trim(preg_replace('/\s+/', ' ', $teks));
        }

        return $hasil;
    }

    private function teksDi(\DOMXPath $xpath, string $kueri): string
    {
        $node = $xpath->query($kueri)->item(0);
        $this->assertNotNull($node, "Elemen {$kueri} harus ada.");

        return trim(preg_replace('/\s+/', ' ', $node->textContent));
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
