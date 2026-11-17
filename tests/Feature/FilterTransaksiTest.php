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

/**
 * F2c D: scope TransaksiSimpanan::filter() dan bilah filter transaksi terbaru di dashboard.
 */
class FilterTransaksiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Anggota $budi;

    private Anggota $siti;

    /** @var array<string, TransaksiSimpanan> */
    private array $trx = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();

        $this->budi = Anggota::factory()->create(['nama' => 'Budi Santoso', 'no_anggota' => 'KSP-2026-0001', 'tanggal_bergabung' => '2026-01-01']);
        $this->siti = Anggota::factory()->create(['nama' => 'Siti Aminah', 'no_anggota' => 'KSP-2026-0002', 'tanggal_bergabung' => '2026-01-01']);

        $this->trx = [
            'budi_pokok_okt' => $this->buatTransaksi($this->budi, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-10-14'),  // 30 hari: batas awal (12 Nov - 29)
            'budi_sukarela_okt' => $this->buatTransaksi($this->budi, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-10-13'), // di luar 30 hari
            'siti_wajib_nov1' => $this->buatTransaksi($this->siti, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-01'),
            'siti_sukarela_nov6' => $this->buatTransaksi($this->siti, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 200000, '2026-11-06'), // 7 hari: batas awal (12 Nov - 6)
            'siti_tarik_nov5' => $this->buatTransaksi($this->siti, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 40000, '2026-11-05'), // di luar 7 hari
            'budi_tarik_hari_ini' => $this->buatTransaksi($this->budi, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 25000, '2026-11-12'),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Scope
    // ---------------------------------------------------------------

    public function test_scope_cari_nama_atau_nomor_anggota(): void
    {
        $this->assertHasil(['cari' => 'budi'], ['budi_pokok_okt', 'budi_sukarela_okt', 'budi_tarik_hari_ini']);
        $this->assertHasil(['cari' => '2026-0002'], ['siti_wajib_nov1', 'siti_sukarela_nov6', 'siti_tarik_nov5']);
        $this->assertHasil(['cari' => '  aminah '], ['siti_wajib_nov1', 'siti_sukarela_nov6', 'siti_tarik_nov5']);
    }

    public function test_scope_jenis_transaksi(): void
    {
        $this->assertHasil(['jenis_transaksi' => 'tarik'], ['siti_tarik_nov5', 'budi_tarik_hari_ini']);
        $this->assertHasil(['jenis_transaksi' => 'setor'], ['budi_pokok_okt', 'budi_sukarela_okt', 'siti_wajib_nov1', 'siti_sukarela_nov6']);
    }

    public function test_scope_jenis_simpanan(): void
    {
        $this->assertHasil(['jenis_simpanan_id' => (string) $this->jenisId(JenisSimpanan::WAJIB)], ['siti_wajib_nov1']);
        $this->assertHasil(['jenis_simpanan_id' => $this->jenisId(JenisSimpanan::POKOK)], ['budi_pokok_okt']);
    }

    public function test_scope_rentang_termasuk_hari_ini_dan_batas_awal(): void
    {
        $this->assertHasil(['rentang' => 'hari_ini'], ['budi_tarik_hari_ini']);
        $this->assertHasil(['rentang' => '7_hari'], ['siti_sukarela_nov6', 'budi_tarik_hari_ini']);
        $this->assertHasil(['rentang' => '30_hari'], ['budi_pokok_okt', 'siti_wajib_nov1', 'siti_sukarela_nov6', 'siti_tarik_nov5', 'budi_tarik_hari_ini']);
        $this->assertHasil(['rentang' => 'bulan_ini'], ['siti_wajib_nov1', 'siti_sukarela_nov6', 'siti_tarik_nov5', 'budi_tarik_hari_ini']);
    }

    public function test_scope_kombinasi_filter(): void
    {
        $this->assertHasil([
            'cari' => 'siti',
            'jenis_transaksi' => 'setor',
            'jenis_simpanan_id' => (string) $this->jenisId(JenisSimpanan::SUKARELA),
            'rentang' => '7_hari',
        ], ['siti_sukarela_nov6']);

        $this->assertHasil(['cari' => 'budi', 'jenis_transaksi' => 'tarik', 'rentang' => 'bulan_ini'], ['budi_tarik_hari_ini']);
        $this->assertHasil(['cari' => 'budi', 'rentang' => 'hari_ini', 'jenis_transaksi' => 'setor'], []);
    }

    public function test_scope_nilai_kosong_dan_tidak_valid_diabaikan(): void
    {
        $semua = array_keys($this->trx);

        $this->assertHasil([], $semua);
        $this->assertHasil(['cari' => '', 'jenis_transaksi' => null, 'jenis_simpanan_id' => '', 'rentang' => '  '], $semua);
        $this->assertHasil([
            'cari' => ['budi'],
            'jenis_transaksi' => 'pinjam',
            'jenis_simpanan_id' => 'abc',
            'rentang' => 'kemarin',
        ], $semua);
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function test_dashboard_menerapkan_filter_dan_mengirim_kontrak_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['cari' => 'siti', 'jenis_transaksi' => 'setor']));

        $response->assertOk();
        $response->assertViewHas('filterTransaksi', [
            'cari' => 'siti',
            'jenis_transaksi' => 'setor',
            'jenis_simpanan_id' => null,
            'rentang' => null,
        ]);
        $this->assertSame(
            [$this->trx['siti_sukarela_nov6']->id, $this->trx['siti_wajib_nov1']->id],
            $response->viewData('transaksiTerbaru')->pluck('id')->all()
        );
        $this->assertSame(
            [JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA],
            $response->viewData('jenisSimpanan')->pluck('nama_jenis')->all()
        );
    }

    public function test_nilai_tidak_valid_dibersihkan_dari_kontrak_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard', [
            'cari' => '   ',
            'jenis_transaksi' => 'pinjam',
            'jenis_simpanan_id' => '999',
            'rentang' => 'kemarin',
        ]));

        $response->assertOk();
        $response->assertViewHas('filterTransaksi', ['cari' => null, 'jenis_transaksi' => null, 'jenis_simpanan_id' => null, 'rentang' => null]);
        $this->assertCount(6, $response->viewData('transaksiTerbaru'));
        $response->assertDontSee('data-reset-filter', false);
        $response->assertDontSee('Menampilkan 10 transaksi terbaru sesuai filter.');
    }

    public function test_form_filter_mempertahankan_nilai_dan_anchor(): void
    {
        $wajibId = (string) $this->jenisId(JenisSimpanan::WAJIB);
        $html = $this->actingAs($this->admin)->get(route('dashboard', [
            'cari' => 'Siti',
            'jenis_transaksi' => 'tarik',
            'jenis_simpanan_id' => $wajibId,
            'rentang' => '30_hari',
        ]))->getContent();
        $xpath = $this->xpath($html);

        $form = $xpath->query('//form[@data-filter-transaksi]');
        $this->assertSame(1, $form->length);
        $this->assertSame('GET', strtoupper($form->item(0)->getAttribute('method')));
        $this->assertSame(route('dashboard').'#transaksi-terbaru', $form->item(0)->getAttribute('action'));
        $this->assertSame(1, $xpath->query('//*[@id="transaksi-terbaru"]')->length);

        $this->assertSame('Siti', $xpath->query('//input[@name="cari"]')->item(0)->getAttribute('value'));
        $this->assertSame('Cari nama atau no. anggota', $xpath->query('//input[@name="cari"]')->item(0)->getAttribute('placeholder'));

        $radio = [];
        foreach ($xpath->query('//input[@type="radio"][@name="jenis_transaksi"]') as $input) {
            $radio[$input->getAttribute('value')] = $input->hasAttribute('checked');
        }
        $this->assertSame(['' => false, 'setor' => false, 'tarik' => true], $radio);

        // F2d E.2: dropdown menu Alpine; nilai lewat input tersembunyi dengan nama parameter yang sama.
        $this->assertSame(0, $xpath->query('//form[@data-filter-transaksi]//select')->length);
        $this->assertSame($wajibId, $xpath->query('//input[@type="hidden"][@name="jenis_simpanan_id"]')->item(0)->getAttribute('value'));
        $this->assertSame('30_hari', $xpath->query('//input[@type="hidden"][@name="rentang"]')->item(0)->getAttribute('value'));

        $opsi = function (string $nama) use ($xpath): array {
            $hasil = [];
            foreach ($xpath->query('//*[@data-dropdown-filter="'.$nama.'"]//*[@role="menu"]/button[@role="menuitemradio"]') as $tombol) {
                $hasil[trim($tombol->textContent)] = $tombol->getAttribute('aria-checked');
            }

            return $hasil;
        };
        $this->assertSame(['Semua jenis' => 'false', 'Pokok' => 'false', 'Wajib' => 'true', 'Sukarela' => 'false'], $opsi('jenis_simpanan_id'));
        $this->assertSame(['Semua waktu' => 'false', 'Hari ini' => 'false', '7 hari' => 'false', '30 hari' => 'true', 'Bulan ini' => 'false'], $opsi('rentang'));

        // Label tombol menampilkan pilihan aktif.
        $this->assertSame('Wajib', trim($xpath->query('//*[@data-dropdown-filter="jenis_simpanan_id"]//*[@data-dropdown-label]')->item(0)->textContent));
        $this->assertSame('30 hari', trim($xpath->query('//*[@data-dropdown-filter="rentang"]//*[@data-dropdown-label]')->item(0)->textContent));
        $this->assertSame('menu', $xpath->query('//*[@data-dropdown-filter="rentang"]//button[@aria-haspopup]')->item(0)->getAttribute('aria-haspopup'));

        // F2d E.3: tombol "Terapkan" tetap ada, tetapi hanya untuk keyboard/pembaca layar (sr-only).
        $terapkan = $xpath->query('//form[@data-filter-transaksi]//button[@type="submit"]')->item(0);
        $this->assertSame('Terapkan', trim($terapkan->textContent));
        $this->assertStringContainsString('sr-only', $terapkan->getAttribute('class'));
        // Segmen terkirim langsung; kolom cari TIDAK terkirim otomatis (H.4.4).
        $this->assertStringContainsString('requestSubmit()', $xpath->query('//input[@name="jenis_transaksi"][@value="setor"]')->item(0)->getAttribute('x-on:change'));
        $this->assertFalse($xpath->query('//input[@name="cari"]')->item(0)->hasAttribute('x-on:input'));
    }

    public function test_tanpa_filter_segmen_semua_terpilih_dan_tanpa_reset(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $xpath = $this->xpath($response->getContent());

        $this->assertTrue($xpath->query('//input[@name="jenis_transaksi"][@value=""]')->item(0)->hasAttribute('checked'));
        $response->assertDontSee('data-reset-filter', false);
        $response->assertDontSee('data-info-filter', false);
        $this->assertCount(6, $response->viewData('transaksiTerbaru'));
    }

    public function test_filter_aktif_menampilkan_reset_tanpa_baris_info(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['cari' => 'budi', 'rentang' => '30_hari']));
        $xpath = $this->xpath($response->getContent());

        // F2d E.5: baris info dihapus.
        $response->assertDontSee('data-info-filter', false);
        $response->assertDontSee('Menampilkan 10 transaksi terbaru sesuai filter.');
        $this->assertSame(route('dashboard').'#transaksi-terbaru', $xpath->query('//a[@data-reset-filter]')->item(0)->getAttribute('href'));
        $this->assertSame('Reset', trim($xpath->query('//a[@data-reset-filter]')->item(0)->textContent));

        // Tautan "Lihat semua" di kepala kartu tetap ke riwayat tanpa filter (F2c I.5).
        $this->assertSame(1, $xpath->query('//a[@href="'.route('transaksi.riwayat').'"][normalize-space()="Lihat semua"]')->length);
        $response->assertDontSee('Lihat semua &rarr;', false);
    }

    public function test_kaki_lihat_semua_hanya_jika_lebih_dari_sepuluh_dengan_jumlah_dan_filter_sama(): void
    {
        // Data awal: 6 transaksi, 3 milik Budi. Tanpa filter 6 <= 10: kaki tidak tampil.
        $this->assertSame(0, $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent())
            ->query('//a[@data-lihat-semua-transaksi]')->length);

        for ($i = 1; $i <= 8; $i++) {
            $this->buatTransaksi($this->budi, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 1000 * $i, '2026-11-0'.$i);
        }

        // Tanpa filter: 14 transaksi.
        $tautan = $this->xpath($this->actingAs($this->admin)->get(route('dashboard'))->getContent())->query('//a[@data-lihat-semua-transaksi]')->item(0);
        $this->assertSame('Lihat semua 14 transaksi', trim(preg_replace('/\s+/', ' ', $tautan->textContent)));
        $this->assertSame(route('transaksi.riwayat'), $tautan->getAttribute('href'));

        // Filter Budi + setor: 2 lama + 8 baru = 10 -> tidak tampil (harus > 10).
        $parameter = ['cari' => 'budi', 'jenis_transaksi' => 'setor'];
        $this->assertSame(0, $this->xpath($this->actingAs($this->admin)->get(route('dashboard', $parameter))->getContent())
            ->query('//a[@data-lihat-semua-transaksi]')->length);

        // Filter Budi saja: 3 lama + 8 baru = 11.
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['cari' => 'budi', 'rentang' => '']));
        $response->assertViewHas('jumlahTransaksiCocok', 11);
        $tautan = $this->xpath($response->getContent())->query('//a[@data-lihat-semua-transaksi]')->item(0);
        $this->assertSame('Lihat semua 11 transaksi', trim(preg_replace('/\s+/', ' ', $tautan->textContent)));
        $this->assertSame(route('transaksi.riwayat', ['cari' => 'budi']), $tautan->getAttribute('href'));

        // Jumlah di kaki sama dengan isi halaman riwayat dengan filter yang sama (H.2.1).
        $this->assertSame(11, $this->actingAs($this->admin)->get($tautan->getAttribute('href'))->viewData('riwayat')->total());
    }

    public function test_kosong_karena_filter_menampilkan_pesan_dan_reset(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['cari' => 'tidak ada orangnya']));

        $response->assertSee('Tidak ada transaksi yang cocok.');
        $response->assertSee('data-reset-filter', false);
        $response->assertDontSee('data-info-filter', false);
        $this->assertCount(0, $response->viewData('transaksiTerbaru'));
    }

    public function test_filter_tidak_mempengaruhi_bagian_lain_dashboard(): void
    {
        $kunci = ['ringkasanAnggota', 'rekapSaldo', 'totalSaldo', 'aktivitasHariIni', 'aktivitasBulanIni', 'trenBulanan',
            'jumlahBelumSetorWajib', 'grafikArus'];

        $tanpa = $this->actingAs($this->admin)->get(route('dashboard'));
        $dengan = $this->actingAs($this->admin)->get(route('dashboard', [
            'cari' => 'budi', 'jenis_transaksi' => 'tarik', 'rentang' => 'hari_ini',
        ]));

        foreach ($kunci as $nama) {
            $this->assertEquals($tanpa->viewData($nama), $dengan->viewData($nama), "{$nama} tidak boleh ikut terfilter.");
        }
        $this->assertEquals(
            $tanpa->viewData('belumSetorWajib')->pluck('id')->all(),
            $dengan->viewData('belumSetorWajib')->pluck('id')->all()
        );
        $this->assertCount(1, $dengan->viewData('transaksiTerbaru'));
    }

    public function test_filter_tetap_tanpa_n_plus_satu(): void
    {
        $parameter = ['cari' => 'a', 'jenis_transaksi' => 'setor', 'rentang' => '30_hari'];
        $hitung = function () use ($parameter): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('dashboard', $parameter))->assertOk();
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $sebelum = $hitung();

        foreach (Anggota::factory()->count(5)->create(['nama' => 'Anggota Baru', 'tanggal_bergabung' => '2026-01-01']) as $lain) {
            $this->buatTransaksi($lain, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 50000, '2026-11-10');
        }

        $this->assertSame($sebelum, $hitung());
    }

    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $kunciHarapan
     */
    private function assertHasil(array $filter, array $kunciHarapan): void
    {
        $harapan = collect($kunciHarapan)->map(fn ($kunci) => $this->trx[$kunci]->id)->sort()->values()->all();
        $hasil = TransaksiSimpanan::query()->filter($filter)->pluck('id')->sort()->values()->all();

        $this->assertSame($harapan, $hasil, 'Filter: '.json_encode($filter));
    }

    private function jenisId(string $nama): int
    {
        return (int) JenisSimpanan::where('nama_jenis', $nama)->value('id');
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
            'jenis_simpanan_id' => $this->jenisId($namaJenis),
            'staff_id' => $this->admin->id,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => 0,
        ]);
    }
}
