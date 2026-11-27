<?php

namespace Tests\Feature;

use App\Exports\LaporanSimpananExport;
use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\LaporanService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

/**
 * Laporan per anggota (FR-16, FR-17; revisi R6–R11).
 */
class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = ['6471000000000001', '6471000000000002', '6471000000000003'];

    private User $admin;

    private SimpananService $service;

    private JenisSimpanan $pokok;

    private JenisSimpanan $wajib;

    private JenisSimpanan $sukarela;

    private Anggota $budi;

    private Anggota $siti;

    private Anggota $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 14:30:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);
        $this->service = app(SimpananService::class);

        $this->pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->firstOrFail();
        $this->wajib = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->firstOrFail();
        $this->sukarela = JenisSimpanan::sukarela();

        $this->skenario();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Skenario uji akhir file 04 (semua bergabung 1 Nov 2026, Andi nonaktif).
     */
    private function skenario(): void
    {
        $this->budi = Anggota::factory()->create(['nama' => 'Budi', 'nik' => self::NIK[0], 'tanggal_bergabung' => '2026-11-01']);
        $this->siti = Anggota::factory()->create(['nama' => 'Siti', 'nik' => self::NIK[1], 'tanggal_bergabung' => '2026-11-01']);
        $this->andi = Anggota::factory()->create(['nama' => 'Andi', 'nik' => self::NIK[2], 'tanggal_bergabung' => '2026-11-01']);

        $this->service->setor($this->budi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->wajib->id, 50000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->sukarela->id, 200000, '2026-11-02', $this->admin->id, null, 'Setoran awal');
        $this->service->tarik($this->budi->id, 75000, '2026-11-05', $this->admin->id, null, 'Biaya sekolah');

        $this->service->setor($this->siti->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->siti->id, $this->sukarela->id, 50000, '2026-11-02', $this->admin->id);

        $this->service->setor($this->andi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function tampilkan(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->get(route('laporan.anggota', $query + ['tampilkan' => '1']))
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->viewData('laporan');
    }

    /**
     * @param  array<string, mixed>  $laporan
     * @return array<string, mixed>
     */
    private function item(array $laporan, string $nama): array
    {
        return $laporan['anggota']->firstWhere('nama', $nama);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, float>
     */
    private function saldo(array $item): array
    {
        return array_column($item['saldo_per_jenis'], 'saldo', 'nama_jenis') + ['total' => $item['total_saldo']];
    }

    // ---------------------------------------------------------------
    // Pratinjau (R6, R7)
    // ---------------------------------------------------------------

    public function test_halaman_tanpa_tampilkan_hanya_menampilkan_form(): void
    {
        $kosong = $this->actingAs($this->admin)->get(route('laporan.anggota'));

        $kosong->assertOk();
        $kosong->assertSee('name="tampilkan" value="1"', false);
        $kosong->assertSee('Semua anggota');
        $kosong->assertDontSee('LAPORAN SIMPANAN PER ANGGOTA');
        $this->assertNull($kosong->viewData('laporan'));
        $this->assertSame(
            ['anggota_id' => null, 'status_anggota' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null],
            $kosong->viewData('filter'),
        );

        // Parameter tanpa `tampilkan`: tetap hanya form, nilai filter dikembalikan, tanpa validasi.
        $denganParameter = $this->actingAs($this->admin)->get(route('laporan.anggota', [
            'anggota_id' => (string) $this->budi->id,
            'tanggal_mulai' => '2026-11-05',
            'tanggal_selesai' => '2026-11-01',
        ]));

        $denganParameter->assertOk()->assertSessionHasNoErrors();
        $this->assertNull($denganParameter->viewData('laporan'));
        $this->assertSame('2026-11-05', $denganParameter->viewData('filter')['tanggal_mulai']);
    }

    // ---------------------------------------------------------------
    // F5 B — kartu filter dan keadaan kosong
    // ---------------------------------------------------------------

    public function test_sebelum_tampilkan_keadaan_kosong_tanpa_tombol_unduh_dan_pratinjau(): void
    {
        $html = $this->actingAs($this->admin)->get(route('laporan.anggota'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $kosong = $xpath->query('//*[@data-laporan-kosong]')->item(0);
        $this->assertNotNull($kosong);
        // R1b C.7: teks keadaan kosong baru, satu kalimat tanpa deskripsi.
        $this->assertSame('Pilih semua atau satu anggota, lalu periodenya.', trim($kosong->textContent));
        $this->assertSame(0, $xpath->query('//*[@data-tombol-unduh]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-pratinjau-laporan]')->length);
        $this->assertSame(0, $xpath->query('//a[@data-reset-laporan]')->length);
        $this->assertStringContainsString('<input type="hidden" name="tampilkan" value="1">', $html);

        // Setelah Tampilkan: tanpa keadaan kosong, pratinjau di dalam bingkai, reset menaut tanpa parameter.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent());
        $this->assertSame(0, $xpath->query('//*[@data-laporan-kosong]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " laporan-pratinjau ")]//*[@data-pratinjau-laporan="per-anggota"]')->length);
        $this->assertSame('Pratinjau cetak', trim($xpath->query('//*[@data-label-pratinjau]')->item(0)->textContent));
        $reset = $xpath->query('//a[@data-reset-laporan]')->item(0);
        $this->assertSame('Reset Filter', trim($reset->textContent));
        $this->assertSame(route('laporan.anggota'), $reset->getAttribute('href'));
    }

    public function test_pemilih_anggota_laporan_berisi_semua_anggota_termasuk_nonaktif_dan_nilai_dari_filter(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent());
        $pemilih = '//form[@action="'.route('laporan.anggota').'"]//*[@data-pilih-anggota-cari]';

        // Label terlihat, semua anggota urut nama termasuk nonaktif. R1b C.2: tanpa opsi "Semua anggota" (diwakili tab mode).
        $this->assertSame('Anggota', trim($xpath->query($pemilih.'//label')->item(0)->textContent));
        $opsi = [];
        foreach ($xpath->query($pemilih.'//*[@data-opsi-anggota]') as $li) {
            $opsi[$li->getAttribute('data-opsi-anggota')] = trim($xpath->query('.//*[@data-label-opsi]', $li)->item(0)->textContent);
        }
        $this->assertSame([
            (string) $this->andi->id => "{$this->andi->no_anggota} - Andi (Nonaktif)",
            (string) $this->budi->id => "{$this->budi->no_anggota} - Budi",
            (string) $this->siti->id => "{$this->siti->no_anggota} - Siti",
        ], $opsi);
        $this->assertSame('', $xpath->query($pemilih.'//input[@type="hidden"][@name="anggota_id"]')->item(0)->getAttribute('value'));
        $this->assertSame('Pilih anggota', trim($xpath->query($pemilih.'//*[@data-anggota-terpilih]')->item(0)->textContent));
        // Tidak ada <select name="anggota_id"> lama.
        $this->assertSame(0, $xpath->query('//select[@name="anggota_id"]')->length);

        // Nilai awal dari $filter: anggota terpilih tampil di pemicu dan input tersembunyi.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => (string) $this->budi->id]))->getContent());
        $this->assertSame((string) $this->budi->id, $xpath->query($pemilih.'//input[@name="anggota_id"]')->item(0)->getAttribute('value'));
        $this->assertSame("{$this->budi->no_anggota} - Budi", trim($xpath->query($pemilih.'//*[@data-anggota-terpilih]')->item(0)->textContent));
    }

    public function test_status_anggota_disabled_saat_anggota_terpilih_dan_error_anggota_tampil_di_bawah_pemicu(): void
    {
        // R1b C.1: select status diganti tab radio di dalam fieldset data-status-laporan; fieldset disabled (server +
        // x-bind mengikuti mode) sehingga status_anggota tidak terkirim saat Satu anggota.
        $status = fn (array $query) => $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', $query))->getContent())
            ->query('//fieldset[@data-status-laporan]')->item(0);

        $semua = $status(['tampilkan' => '1']);
        $this->assertFalse($semua->hasAttribute('disabled'));
        $this->assertSame("mode !== 'semua'", $semua->getAttribute('x-bind:disabled'));
        $this->assertTrue($status(['tampilkan' => '1', 'anggota_id' => (string) $this->budi->id])->hasAttribute('disabled'));
        $this->assertSame(0, $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent())->query('//select[@name="status_anggota"]')->length);

        // Form mengikuti event anggota-dipilih dari partial.
        $html = $this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent();
        $this->assertStringContainsString('x-on:anggota-dipilih="anggota = $event.detail.id;', $html);

        // Error anggota_id (exists) tampil di bawah pemicu partial.
        $html = $this->actingAs($this->admin)
            ->from(route('laporan.anggota'))
            ->followingRedirects()
            ->get(route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => '999']))
            ->getContent();
        $error = $this->xpath($html)->query('//*[@data-pilih-anggota-cari]//p[contains(@class, "text-red-700")]');
        $this->assertSame(1, $error->length);
    }

    // ---------------------------------------------------------------
    // R1b C — filter laporan per anggota yang jelas
    // ---------------------------------------------------------------

    public function test_tab_mode_laporan_dan_keadaan_awal_mengikuti_filter(): void
    {
        $mode = function (array $query): array {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', $query))->getContent());
            $tab = [];
            foreach ($xpath->query('//*[@data-mode-laporan]/button') as $tombol) {
                $this->assertSame('button', $tombol->getAttribute('type'));
                $tab[trim($tombol->textContent)] = $tombol->getAttribute('aria-pressed');
            }

            return [$tab, $xpath];
        };

        [$tab, $xpath] = $mode([]);
        $this->assertSame(['Semua anggota' => 'true', 'Satu anggota' => 'false'], $tab);
        $this->assertStringContainsString("mode: 'semua'", $xpath->query('//form[@action="'.route('laporan.anggota').'"]')->item(0)->getAttribute('x-data'));
        // Status tampil (tab garis bawah kecil, bukan select); pemilih tersembunyi sampai Alpine (x-cloak).
        $this->assertSame(['', 'aktif', 'nonaktif'], array_map(fn ($el) => $el->getAttribute('value'), iterator_to_array($xpath->query('//*[@data-status-laporan]//input[@type="radio"][@name="status_anggota"]'))));
        $this->assertSame(['Semua status', 'Aktif', 'Nonaktif'], array_map(fn ($el) => trim($el->textContent), iterator_to_array($xpath->query('//*[@data-status-laporan]//label'))));
        $this->assertSame('', $xpath->query('//input[@name="status_anggota"][@checked]')->item(0)->getAttribute('value'), 'Bawaan Semua status.');
        $this->assertFalse($xpath->query('//*[@data-status-laporan]')->item(0)->hasAttribute('x-cloak'));
        $this->assertTrue($xpath->query('//*[@data-pemilih-laporan]')->item(0)->hasAttribute('x-cloak'));

        [$tab, $xpath] = $mode(['status_anggota' => 'nonaktif']);
        $this->assertSame('true', $tab['Semua anggota']);
        $this->assertSame('nonaktif', $xpath->query('//input[@name="status_anggota"][@checked]')->item(0)->getAttribute('value'));

        [$tab, $xpath] = $mode(['tampilkan' => '1', 'anggota_id' => (string) $this->budi->id]);
        $this->assertSame(['Semua anggota' => 'false', 'Satu anggota' => 'true'], $tab);
        $this->assertTrue($xpath->query('//*[@data-status-laporan]')->item(0)->hasAttribute('x-cloak'));
        $this->assertFalse($xpath->query('//*[@data-pemilih-laporan]')->item(0)->hasAttribute('x-cloak'));
    }

    public function test_anggota_id_dan_status_dinonaktifkan_sesuai_mode(): void
    {
        $xpath = fn (array $query) => $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', $query))->getContent());

        // Semua anggota: anggota_id disabled (tidak terkirim), status aktif.
        $semua = $xpath([]);
        $id = $semua->query('//*[@data-pilih-anggota-cari]/input[@name="anggota_id"]')->item(0);
        $this->assertTrue($id->hasAttribute('disabled'));
        $this->assertSame("mode !== 'satu'", $id->getAttribute('x-bind:disabled'));
        $this->assertFalse($semua->query('//fieldset[@data-status-laporan]')->item(0)->hasAttribute('disabled'));

        // Satu anggota: anggota_id terkirim, fieldset status disabled (radio di dalamnya tidak terkirim).
        $satu = $xpath(['tampilkan' => '1', 'anggota_id' => (string) $this->budi->id, 'status_anggota' => 'nonaktif']);
        $this->assertFalse($satu->query('//*[@data-pilih-anggota-cari]/input[@name="anggota_id"]')->item(0)->hasAttribute('disabled'));
        $this->assertTrue($satu->query('//fieldset[@data-status-laporan]')->item(0)->hasAttribute('disabled'));
        $this->assertSame("mode !== 'semua'", $satu->query('//fieldset[@data-status-laporan]')->item(0)->getAttribute('x-bind:disabled'));

        // Setor/tarik tidak ikut berubah: input anggota_id tanpa disabled/x-bind.
        foreach ([route('transaksi.setor.create'), route('transaksi.tarik.create')] as $url) {
            $input = $this->xpath($this->actingAs($this->admin)->get($url)->getContent())->query('//*[@data-pilih-anggota-cari]/input[@name="anggota_id"]')->item(0);
            $this->assertFalse($input->hasAttribute('disabled'), $url);
            $this->assertFalse($input->hasAttribute('x-bind:disabled'), $url);
        }
    }

    public function test_tanpa_anggota_terpilih_di_mode_satu_muncul_pilih_anggota_dulu(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent());
        $form = $xpath->query('//form[@action="'.route('laporan.anggota').'"]')->item(0);

        // Kiriman dibatalkan (defaultPrevented) dan pesan tampil; cegah-kirim-ganda melewati event yang sudah dibatalkan.
        $this->assertSame("if (mode === 'satu' && anggota === '') { \$event.preventDefault(); galat = true }", $form->getAttribute('x-on:submit'));
        $pesan = $xpath->query('//*[@data-pemilih-laporan]//*[@data-galat-pilih-anggota]')->item(0);
        $this->assertSame('Pilih anggota dulu.', trim($pesan->textContent));
        $this->assertSame('galat', $pesan->getAttribute('x-show'));
        $this->assertTrue($pesan->hasAttribute('x-cloak'));
        // Pesan berada di bawah pemilih.
        $this->assertSame(1, $xpath->query('//*[@data-pemilih-laporan]/*[@data-pilih-anggota-cari]/following-sibling::*[@data-galat-pilih-anggota]')->length);
    }

    public function test_pemilih_laporan_panel_lebar_dan_info_bergabung_transaksi_terakhir_tanpa_n_plus_1(): void
    {
        $dewi = Anggota::factory()->create(['nama' => 'Dewi', 'tanggal_bergabung' => '2026-11-10']);

        DB::enableQueryLog();
        $respons = $this->actingAs($this->admin)->get(route('laporan.anggota', ['anggota_id' => (string) $this->budi->id]));
        $kueri = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // Satu query anggota dengan subquery MAX, tanpa query transaksi per anggota.
        $this->assertSame(1, $kueri->filter(fn ($q) => str_contains($q, 'from "anggota"') && str_contains($q, 'MAX(tanggal_transaksi)'))->count());
        $this->assertSame(0, $kueri->filter(fn ($q) => str_starts_with($q, 'select * from "transaksi_simpanan"'))->count());

        $daftar = $respons->viewData('anggotaList');
        $this->assertSame('2026-11-05', Carbon::parse($daftar->firstWhere('id', $this->budi->id)->transaksi_terakhir)->toDateString());
        $this->assertNull($daftar->firstWhere('id', $dewi->id)->transaksi_terakhir);

        $xpath = $this->xpath($respons->getContent());
        $opsi = fn (Anggota $a) => $xpath->query('//*[@data-opsi-anggota="'.$a->id.'"]')->item(0);
        $this->assertSame('2026-11-01', $opsi($this->budi)->getAttribute('data-bergabung'));
        $this->assertSame('Bergabung 1 November 2026 · Transaksi terakhir 5 November 2026', $opsi($this->budi)->getAttribute('data-info'));
        $this->assertSame('Bergabung 10 November 2026 · Belum ada transaksi', $opsi($dewi)->getAttribute('data-info'));
        $this->assertSame('Bergabung 1 November 2026 · Transaksi terakhir 1 November 2026', $opsi($this->andi)->getAttribute('data-info'));

        // Baris info untuk anggota terpilih, di bawah pemilih.
        $info = $xpath->query('//*[@data-pilih-anggota-cari]//*[@data-info-anggota-laporan]')->item(0);
        $this->assertSame('Bergabung 1 November 2026 · Transaksi terakhir 5 November 2026', trim($info->textContent));
        $this->assertStringContainsString('text-xs', $info->getAttribute('class'));
        $this->assertStringContainsString('text-stone-500', $info->getAttribute('class'));

        // Panel dilebarkan supaya nama tidak terpotong.
        $panel = $xpath->query('//*[@data-pilih-anggota-cari]/div[@x-show="buka"]')->item(0);
        foreach (['w-max', 'min-w-full', 'max-w-[32rem]'] as $kelas) {
            $this->assertContains($kelas, explode(' ', $panel->getAttribute('class')), $kelas);
        }
        $this->assertNotContains('inset-x-0', explode(' ', $panel->getAttribute('class')));

        // Setor tidak berubah: panel inset-x-0, tanpa data-info dan baris info.
        $setor = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.setor.create'))->getContent());
        $this->assertContains('inset-x-0', explode(' ', $setor->query('//*[@data-pilih-anggota-cari]/div[@x-show="buka"]')->item(0)->getAttribute('class')));
        $this->assertSame(0, $setor->query('//*[@data-info]|//*[@data-info-anggota-laporan]')->length);
    }

    public function test_periode_cepat_berisi_rentang_yang_benar_termasuk_batas_tanggal(): void
    {
        $kasus = [
            '2026-11-12 14:30:00' => [
                'Bulan ini' => ['2026-11-01', '2026-11-12'],
                'Bulan lalu' => ['2026-10-01', '2026-10-31'],
                '3 bulan terakhir' => ['2026-08-12', '2026-11-12'],
                'Tahun ini' => ['2026-01-01', '2026-11-12'],
                'Tahun lalu' => ['2025-01-01', '2025-12-31'],
            ],
            // Awal tahun: bulan lalu dan tahun lalu menyeberang tahun; rentang berjalan satu hari.
            '2026-01-01 00:05:00' => [
                'Bulan ini' => ['2026-01-01', '2026-01-01'],
                'Bulan lalu' => ['2025-12-01', '2025-12-31'],
                '3 bulan terakhir' => ['2025-10-01', '2026-01-01'],
                'Tahun ini' => ['2026-01-01', '2026-01-01'],
                'Tahun lalu' => ['2025-01-01', '2025-12-31'],
            ],
            // Akhir bulan panjang: mundur 3 bulan tanpa overflow (sama dengan Riwayat), bulan lalu Februari penuh.
            '2026-05-31 23:59:00' => [
                'Bulan ini' => ['2026-05-01', '2026-05-31'],
                'Bulan lalu' => ['2026-04-01', '2026-04-30'],
                '3 bulan terakhir' => ['2026-02-28', '2026-05-31'],
                'Tahun ini' => ['2026-01-01', '2026-05-31'],
                'Tahun lalu' => ['2025-01-01', '2025-12-31'],
            ],
            '2028-03-31 08:00:00' => [
                'Bulan ini' => ['2028-03-01', '2028-03-31'],
                'Bulan lalu' => ['2028-02-01', '2028-02-29'],
                '3 bulan terakhir' => ['2027-12-31', '2028-03-31'],
                'Tahun ini' => ['2028-01-01', '2028-03-31'],
                'Tahun lalu' => ['2027-01-01', '2027-12-31'],
            ],
        ];

        foreach ($kasus as $sekarang => $harapan) {
            Carbon::setTestNow($sekarang);
            $periode = $this->actingAs($this->admin)->get(route('laporan.anggota'))->viewData('periodeCepat');
            $this->assertSame(['Semua waktu' => [null, null]] + $harapan, $periode, $sekarang);
            $this->assertSame($periode, $this->actingAs($this->admin)->get(route('laporan.keseluruhan'))->viewData('periodeCepat'), $sekarang);
        }
    }

    public function test_tombol_periode_cepat_mengisi_tanpa_kirim_dan_menandai_yang_cocok(): void
    {
        $tombol = function (array $query): array {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota', $query))->getContent());
            $hasil = [];
            foreach ($xpath->query('//*[@data-periode-cepat]//button') as $el) {
                $this->assertSame('button', $el->getAttribute('type'), 'Tombol cepat tidak mengirim form.');
                $kelas = explode(' ', $el->getAttribute('class'));
                $this->assertContains('rounded-md', $kelas);
                $this->assertContains('h-8', $kelas, 'Ukuran sm.');
                $this->assertContains('aria-pressed:border-arang', $kelas);
                $hasil[trim($el->textContent)] = $el->getAttribute('aria-pressed');
            }

            return array_keys(array_filter($hasil, fn ($v) => $v === 'true'));
        };

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent());
        $this->assertSame(
            ['Semua waktu', 'Bulan ini', 'Bulan lalu', '3 bulan terakhir', 'Tahun ini', 'Tahun lalu', 'Pilih tanggal…'],
            array_map(fn ($el) => trim($el->textContent), iterator_to_array($xpath->query('//*[@data-periode-cepat]//button'))),
        );
        // Tombol cepat berada di atas kolom tanggal.
        $this->assertSame(1, $xpath->query('//*[@data-periode-cepat]//button/ancestor::div[1]/parent::div/following-sibling::div//input[@name="tanggal_mulai"]')->length);
        $this->assertSame("pilih('Bulan ini')", $xpath->query('//*[@data-periode-cepat]//button[@data-periode="Bulan ini"]')->item(0)->getAttribute('x-on:click'));
        $this->assertSame('$refs.mulai.focus()', $xpath->query('//*[@data-periode-cepat]//button[@data-periode="kustom"]')->item(0)->getAttribute('x-on:click'));

        $this->assertSame(['Semua waktu'], $tombol([]));
        $this->assertSame(['Bulan ini'], $tombol(['tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-12']));
        $this->assertSame(['Tahun lalu'], $tombol(['tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-12-31']));
        $this->assertSame(['Pilih tanggal…'], $tombol(['tanggal_mulai' => '2026-11-03', 'tanggal_selesai' => '2026-11-04']));

        // Mode Satu anggota: rentang dinaikkan ke tanggal bergabung (jawaban 5a).
        $dewi = Anggota::factory()->create(['nama' => 'Dewi', 'tanggal_bergabung' => '2026-11-10']);
        $this->assertSame(['Bulan ini'], $tombol(['anggota_id' => (string) $dewi->id, 'tanggal_mulai' => '2026-11-10', 'tanggal_selesai' => '2026-11-12']));
    }

    public function test_kolom_tanggal_max_hari_ini_dan_min_tanggal_bergabung_di_mode_satu(): void
    {
        $dewi = Anggota::factory()->create(['nama' => 'Dewi', 'tanggal_bergabung' => '2026-11-10']);
        $kolom = fn (string $url) => iterator_to_array($this->xpath($this->actingAs($this->admin)->get($url)->getContent())
            ->query('//input[@name="tanggal_mulai" or @name="tanggal_selesai"]'));

        foreach ($kolom(route('laporan.anggota')) as $el) {
            $this->assertSame('2026-11-12', $el->getAttribute('max'));
            $this->assertFalse($el->hasAttribute('min'), 'Semua anggota tanpa min.');
            $this->assertSame('batas() || null', $el->getAttribute('x-bind:min'));
        }
        foreach ($kolom(route('laporan.anggota', ['anggota_id' => (string) $dewi->id])) as $el) {
            $this->assertSame('2026-11-12', $el->getAttribute('max'));
            $this->assertSame('2026-11-10', $el->getAttribute('min'));
        }
        // Batas min klien mengikuti anggota terpilih (event anggota-dipilih membawa tanggal bergabung).
        $html = $this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent();
        $this->assertStringContainsString("bergabung: el.dataset.bergabung ?? ''", $html);
        $this->assertStringContainsString("return mode === 'satu' && bergabung ? bergabung : '' || '';", html_entity_decode($html, ENT_QUOTES));

        // Laporan keseluruhan: max hari ini, tanpa min.
        $keseluruhan = $kolom(route('laporan.keseluruhan'));
        $this->assertCount(2, $keseluruhan);
        foreach ($keseluruhan as $el) {
            $this->assertSame('2026-11-12', $el->getAttribute('max'));
            $this->assertFalse($el->hasAttribute('min'));
        }
        // Validasi server tetap: tanggal masa depan tidak ditolak oleh server (aturan FormRequest tidak berubah).
        $this->tampilkan(['tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-12-31']);
    }

    public function test_tombol_cepat_ada_di_laporan_keseluruhan_dan_filter_lain_tetap(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.keseluruhan', ['tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-31']))->getContent());
        $form = '//form[@action="'.route('laporan.keseluruhan').'"]';

        $this->assertSame(1, $xpath->query($form.'//*[@data-periode-cepat]')->length);
        $this->assertSame('true', $xpath->query($form.'//*[@data-periode-cepat]//button[@data-periode="Bulan lalu"]')->item(0)->getAttribute('aria-pressed'));
        foreach (['jenis_simpanan_id', 'jenis_transaksi', 'status_anggota'] as $nama) {
            $this->assertSame(1, $xpath->query($form.'//select[@name="'.$nama.'"]')->length, $nama);
        }
    }

    public function test_nama_field_form_laporan_per_anggota_tetap(): void
    {
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('laporan.anggota'))->getContent());
        $nama = array_values(array_unique(array_map(fn ($el) => $el->getAttribute('name'), iterator_to_array($xpath->query('//form[@action="'.route('laporan.anggota').'"]//*[@name]')))));

        $this->assertSame(['tampilkan', 'status_anggota', 'anggota_id', 'tanggal_mulai', 'tanggal_selesai'], $nama);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    public function test_semua_anggota_saat_anggota_kosong(): void
    {
        $laporan = $this->tampilkan();

        $this->assertSame(
            [$this->budi->no_anggota, $this->siti->no_anggota, $this->andi->no_anggota],
            $laporan['anggota']->pluck('no_anggota')->all(),
        );
        $this->assertSame([4, 2, 1], $laporan['anggota']->map(fn ($a) => $a['transaksi']->count())->all());
        $this->assertSame('Anggota: Semua; Status: Semua', $laporan['filter_label']);
        $this->assertSame('Seluruh periode', $laporan['periode']['label']);
        $this->assertSame('Admin Koperasi', $laporan['dicetak_oleh']);
        $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA], $laporan['jenis_simpanan']);
    }

    public function test_satu_anggota_dengan_transaksi_urut_kronologis(): void
    {
        $laporan = $this->tampilkan(['anggota_id' => (string) $this->budi->id]);

        $this->assertCount(1, $laporan['anggota']);
        $budi = $laporan['anggota']->first();

        $this->assertSame('Budi', $budi['nama']);
        $this->assertSame('aktif', $budi['status']);
        $this->assertSame('2026-11-01', $budi['tanggal_bergabung']->format('Y-m-d'));
        $this->assertSame("Anggota: {$this->budi->no_anggota} - Budi", $laporan['filter_label']);

        $ids = $budi['transaksi']->pluck('id')->all();
        $urut = $ids;
        sort($urut);
        $this->assertSame($urut, $ids);
        $this->assertSame(
            ['2026-11-01', '2026-11-01', '2026-11-02', '2026-11-05'],
            $budi['transaksi']->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d'))->all(),
        );
        $this->assertSame([null, null, 'Setoran awal', 'Biaya sekolah'], $budi['transaksi']->pluck('keterangan')->all());
    }

    public function test_filter_status_hanya_berlaku_saat_semua_anggota(): void
    {
        $nonaktif = $this->tampilkan(['status_anggota' => 'nonaktif']);
        $this->assertSame(['Andi'], $nonaktif['anggota']->pluck('nama')->all());
        $this->assertSame('Anggota: Semua; Status: Nonaktif', $nonaktif['filter_label']);

        $aktif = $this->tampilkan(['status_anggota' => 'aktif']);
        $this->assertSame(['Budi', 'Siti'], $aktif['anggota']->pluck('nama')->all());

        // Saat satu anggota dipilih, status diabaikan.
        $budi = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'status_anggota' => 'nonaktif']);
        $this->assertSame(['Budi'], $budi['anggota']->pluck('nama')->all());
    }

    public function test_filter_tanggal_inklusif(): void
    {
        $laporan = $this->tampilkan([
            'anggota_id' => (string) $this->budi->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-03',
        ]);

        $this->assertCount(3, $this->item($laporan, 'Budi')['transaksi']);
        $this->assertSame('1 November 2026 s.d. 3 November 2026', $laporan['periode']['label']);

        $satuHari = $this->tampilkan(['tanggal_mulai' => '2026-11-05', 'tanggal_selesai' => '2026-11-05']);
        $this->assertSame([1, 0, 0], $satuHari['anggota']->map(fn ($a) => $a['transaksi']->count())->all());
    }

    public function test_saldo_per_akhir_periode_mengabaikan_transaksi_setelah_tanggal_selesai(): void
    {
        $sampai = $this->tampilkan(['tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-03']);

        $this->assertSame([
            JenisSimpanan::POKOK => 100000.0,
            JenisSimpanan::WAJIB => 50000.0,
            JenisSimpanan::SUKARELA => 200000.0,
            'total' => 350000.0,
        ], $this->saldo($this->item($sampai, 'Budi')));

        $penuh = $this->tampilkan();

        $this->assertSame(125000.0, $this->saldo($this->item($penuh, 'Budi'))[JenisSimpanan::SUKARELA]);
        $this->assertSame(275000.0, $this->saldo($this->item($penuh, 'Budi'))['total']);
        $this->assertSame(150000.0, $this->saldo($this->item($penuh, 'Siti'))['total']);
        $this->assertSame(100000.0, $this->saldo($this->item($penuh, 'Andi'))['total']);
    }

    public function test_label_periode_sejak_dan_sampai(): void
    {
        $sejak = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'tanggal_mulai' => '2026-11-02']);
        $this->assertSame('Sejak 2 November 2026', $sejak['periode']['label']);
        $this->assertCount(2, $this->item($sejak, 'Budi')['transaksi']);

        $sampai = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'tanggal_selesai' => '2026-11-01']);
        $this->assertSame('Sampai 1 November 2026', $sampai['periode']['label']);
        $this->assertCount(2, $this->item($sampai, 'Budi')['transaksi']);
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->from(route('laporan.anggota'))
            ->get(route('laporan.anggota', [
                'tampilkan' => '1',
                'tanggal_mulai' => '2026-11-05',
                'tanggal_selesai' => '2026-11-01',
            ]))
            ->assertRedirect(route('laporan.anggota'))
            ->assertSessionHasErrors(['tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
    }

    public function test_nilai_filter_tidak_valid_ditolak(): void
    {
        foreach (['anggota_id' => '999', 'status_anggota' => 'keluar', 'tanggal_mulai' => '05-11-2026'] as $field => $nilai) {
            $this->actingAs($this->admin)
                ->from(route('laporan.anggota'))
                ->get(route('laporan.anggota', ['tampilkan' => '1', $field => $nilai]))
                ->assertRedirect(route('laporan.anggota'))
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($this->admin)
            ->get(route('laporan.anggota.pdf', ['anggota_id' => '999']))
            ->assertSessionHasErrors('anggota_id');
    }

    public function test_anggota_nonaktif_tetap_bisa_dilaporkan(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => (string) $this->andi->id]));

        $response->assertOk();
        $this->assertSame(['Andi'], $response->viewData('laporan')['anggota']->pluck('nama')->all());
        $this->assertTrue($response->viewData('anggotaList')->contains('id', $this->andi->id));
    }

    // ---------------------------------------------------------------
    // Tampilan (R8)
    // ---------------------------------------------------------------

    public function test_pratinjau_memakai_tata_letak_blok_per_anggota(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.anggota', [
            'tampilkan' => '1',
            'tanggal_mulai' => '2026-11-05',
        ]));

        $response->assertSeeInOrder([
            'LAPORAN SIMPANAN PER ANGGOTA',
            'Periode : Sejak 5 November 2026',
            'Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama',
            // Blok 1: Budi
            "1. {$this->budi->no_anggota} — Budi", 'Bergabung: 1 November 2026', 'Status:', 'Aktif',
            JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA, 'Total Saldo',
            'Rp 100.000', 'Rp 50.000', 'Rp 125.000', 'Rp 275.000',
            "RIWAYAT TRANSAKSI — Budi ({$this->budi->no_anggota})",
            'No', 'Tanggal', 'Jenis Simpanan', 'Jenis Transaksi', 'Jumlah', 'Saldo Akhir', 'Dicatat Oleh', 'Catatan',
            'Biaya sekolah',
            'Total setor periode: Rp 0', 'Total tarik periode: Rp 75.000',
            // Blok 2: Siti, tanpa transaksi pada periode
            "2. {$this->siti->no_anggota} — Siti",
            "RIWAYAT TRANSAKSI — Siti ({$this->siti->no_anggota})",
            'Tidak ada transaksi pada periode ini.',
            'Total setor periode: Rp 0',
            // Blok 3: Andi (nonaktif)
            "3. {$this->andi->no_anggota} — Andi", 'Nonaktif',
            'Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi',
        ], false);

        $html = $response->getContent();
        $this->assertSame(3, substr_count($html, '<div class="lap-blok"'));
        $this->assertStringContainsString('<span class="lap-hijau">Aktif</span>', $html);
        $this->assertStringContainsString('<span class="lap-merah">Nonaktif</span>', $html);
        $this->assertStringContainsString('<td class="lap-merah">Tarik</td>', $html);
        // Kisi kolom bersama lama sudah tidak ada.
        $this->assertStringNotContainsString('Nama Anggota', $html);

        $semua = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent();
        $this->assertStringContainsString('<td class="lap-hijau">Setor</td>', $semua);
    }

    public function test_struktur_blok_setiap_riwayat_berada_di_blok_anggotanya(): void
    {
        $laporan = app(LaporanService::class)->perAnggota([]) + ['dicetak_oleh' => 'Admin Koperasi'];

        $dokumen = [
            'pratinjau' => $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent(),
            'pdf' => view('laporan.pdf', $laporan)->render(),
        ];

        $harapan = [];
        foreach ([$this->budi, $this->siti, $this->andi] as $anggota) {
            $harapan[$anggota->no_anggota] = [
                'nama' => $anggota->nama,
                'transaksi' => $anggota->transaksiSimpanan()->orderBy('tanggal_transaksi')->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        foreach ($dokumen as $tempat => $html) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);

            $blok = $xpath->query('//div[@class="lap-blok"]');
            $this->assertSame(3, $blok->length, "Jumlah blok di {$tempat}.");

            $nomor = 0;
            foreach ($blok as $elemen) {
                $nomor++;
                $noAnggota = $elemen->getAttribute('data-anggota');
                $this->assertArrayHasKey($noAnggota, $harapan);

                $pita = trim($xpath->query('.//td[@data-pita-judul]', $elemen)->item(0)->textContent);
                $this->assertStringStartsWith("{$nomor}. {$noAnggota} — {$harapan[$noAnggota]['nama']}", $pita, "Pita judul di {$tempat}.");

                // Setiap blok punya tabelnya sendiri, masing-masing satu.
                foreach (['lap-pita-judul', 'lap-saldo', 'lap-pita-riwayat', 'lap-riwayat', 'lap-ringkasan-anggota'] as $kelas) {
                    $this->assertSame(1, $xpath->query(".//table[contains(@class, '{$kelas}')]", $elemen)->length, "{$kelas} di blok {$noAnggota} ({$tempat}).");
                }
                $this->assertStringContainsString(
                    "RIWAYAT TRANSAKSI — {$harapan[$noAnggota]['nama']} ({$noAnggota})",
                    $xpath->query(".//table[contains(@class, 'lap-pita-riwayat')]", $elemen)->item(0)->textContent,
                );

                // Semua baris riwayat anggota ini ada di dalam bloknya, dan hanya miliknya.
                $idDiBlok = [];
                foreach ($xpath->query('.//tr[@data-transaksi]', $elemen) as $tr) {
                    $idDiBlok[] = $tr->getAttribute('data-transaksi');
                }
                $this->assertSame($harapan[$noAnggota]['transaksi'], $idDiBlok, "Riwayat blok {$noAnggota} di {$tempat}.");
            }

            // Tidak ada baris riwayat di luar blok.
            $this->assertSame(0, $xpath->query('//tr[@data-transaksi][not(ancestor::div[@class="lap-blok"])]')->length);
        }
    }

    public function test_ringkasan_total_setor_dan_tarik_per_anggota_sesuai_periode(): void
    {
        $periode = $this->tampilkan(['tanggal_mulai' => '2026-11-02']);

        $this->assertSame([200000.0, 75000.0], [$this->item($periode, 'Budi')['total_setor_periode'], $this->item($periode, 'Budi')['total_tarik_periode']]);
        $this->assertSame([50000.0, 0.0], [$this->item($periode, 'Siti')['total_setor_periode'], $this->item($periode, 'Siti')['total_tarik_periode']]);
        $this->assertSame([0.0, 0.0], [$this->item($periode, 'Andi')['total_setor_periode'], $this->item($periode, 'Andi')['total_tarik_periode']]);

        $penuh = $this->tampilkan();
        $this->assertSame([350000.0, 75000.0], [$this->item($penuh, 'Budi')['total_setor_periode'], $this->item($penuh, 'Budi')['total_tarik_periode']]);

        $html = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1', 'tanggal_mulai' => '2026-11-02']))->getContent();
        $this->assertStringContainsString('<td data-total-setor-periode>Total setor periode: Rp 200.000</td>', $html);
        $this->assertStringContainsString('<td data-total-tarik-periode>Total tarik periode: Rp 75.000</td>', $html);
    }

    public function test_pratinjau_menampilkan_tombol_unduh_dengan_filter_sama(): void
    {
        $query = ['anggota_id' => (string) $this->budi->id, 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-03'];

        $this->actingAs($this->admin)
            ->get(route('laporan.anggota', $query + ['tampilkan' => '1']))
            ->assertSee(route('laporan.anggota.pdf', $query))
            ->assertSee(route('laporan.anggota.excel', $query));
    }

    public function test_tombol_unduh_memakai_komponen_tombol(): void
    {
        $query = ['anggota_id' => (string) $this->budi->id, 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-03'];
        $xpath = $this->xpath($this->actingAs($this->admin)
            ->get(route('laporan.anggota', $query + ['tampilkan' => '1']))
            ->getContent());

        // F5 C / H.4.3: tepat satu <a> per jenis, teks persis, href = route dengan query filter yang sama (dibandingkan setelah parse).
        foreach (['pdf' => ['Unduh PDF', 'laporan.anggota.pdf'], 'excel' => ['Unduh Excel', 'laporan.anggota.excel']] as $jenis => [$teks, $nama]) {
            $tombol = $xpath->query('//a[@data-tombol-unduh="'.$jenis.'"]');
            $this->assertSame(1, $tombol->length, $jenis);
            $this->assertSame(1, $xpath->query('//*[@data-tombol-unduh="'.$jenis.'"]')->length, $jenis);
            $this->assertSame($teks, trim($tombol->item(0)->textContent));

            $href = $tombol->item(0)->getAttribute('href');
            $this->assertSame(strtok(route($nama), '?'), strtok($href, '?'));
            parse_str((string) parse_url($href, PHP_URL_QUERY), $parameter);
            ksort($parameter);
            $harapan = $query;
            ksort($harapan);
            $this->assertSame($harapan, $parameter);

            // Komponen x-ui.button secondary (bukan kelas Breeze) di actions header; R1 B.3/C.1: sudut rounded-md, tanpa ikon.
            $kelas = $tombol->item(0)->getAttribute('class');
            $this->assertStringContainsString('rounded-md', $kelas);
            $this->assertStringNotContainsString('rounded-full', $kelas);
            $this->assertStringContainsString('border-stone-300', $kelas);
            $this->assertStringNotContainsString('bg-gray-800', $kelas);
            $this->assertSame(0, $xpath->query('//a[@data-tombol-unduh="'.$jenis.'"]//*[local-name()="svg"]')->length);
        }
        $this->assertSame(0, $xpath->query('//*[@data-pratinjau-laporan]//*[@data-tombol-unduh]')->length);
    }

    public function test_view_pdf_struktur_sama_dan_kepala_berulang(): void
    {
        $laporan = app(LaporanService::class)->perAnggota(['tanggal_selesai' => '2026-11-03']);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';

        $html = view('laporan.pdf', $laporan)->render();

        // Kepala fixed (diulang tiap halaman), area kop kosong, thead berulang, baris tidak terpotong.
        $this->assertStringContainsString('header { position: fixed;', $html);
        $this->assertStringContainsString('data-area-kop', $html);
        $this->assertStringContainsString('display: table-header-group', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);

        $this->assertStringContainsString('LAPORAN SIMPANAN PER ANGGOTA', $html);
        $this->assertStringContainsString('Periode : Sampai 3 November 2026', $html);
        $this->assertStringContainsString('Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama', $html);
        $this->assertStringContainsString('RIWAYAT TRANSAKSI', $html);
        $this->assertStringContainsString('Setoran awal', $html);
        $this->assertStringContainsString('Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $html);
    }

    // ---------------------------------------------------------------
    // Ekspor (R8, R9)
    // ---------------------------------------------------------------

    public function test_unduh_pdf_nama_file_sesuai_mode(): void
    {
        $satu = $this->actingAs($this->admin)->get(route('laporan.anggota.pdf', ['anggota_id' => $this->budi->id]));

        $satu->assertOk();
        $satu->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('attachment;', $satu->headers->get('content-disposition'));
        $this->assertStringContainsString("Laporan-Simpanan-{$this->budi->no_anggota}-20261112.pdf", $satu->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $satu->getContent());

        $semua = $this->actingAs($this->admin)->get(route('laporan.anggota.pdf', ['status_anggota' => 'aktif']));

        $semua->assertOk();
        $this->assertStringContainsString('Laporan-Simpanan-Semua-Anggota-20261112.pdf', $semua->headers->get('content-disposition'));
    }

    /**
     * Baris lembar menurut penanda metadata blok: 'pita-judul', 'pita-riwayat',
     * 'saldo', 'riwayat', 'ringkasan', atau teks baris penuh.
     *
     * @return array<int, array<int, mixed>>
     */
    private function barisLembar(LembarLaporan $lembar, string $jenis): array
    {
        $baris = $lembar->array();
        $hasil = [];

        foreach ($lembar->meta() as $nomor => $meta) {
            $cocok = match ($jenis) {
                'pita-judul' => ($meta['pita'] ?? null) === 'judul',
                'pita-riwayat' => ($meta['pita'] ?? null) === 'riwayat',
                'saldo', 'riwayat', 'ringkasan' => ! empty($meta[$jenis]),
                default => $meta['jenis'] === 'penuh' && $baris[$nomor - 1] === [$jenis],
            };

            if ($cocok) {
                $hasil[] = $baris[$nomor - 1];
            }
        }

        return $hasil;
    }

    public function test_unduh_excel_satu_anggota(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', [
            'anggota_id' => $this->budi->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-03',
        ]))->assertOk();

        Excel::assertDownloaded("Laporan-Simpanan-{$this->budi->no_anggota}-20261112.xlsx", function (LaporanSimpananExport $export) {
            $lembar = $export->lembar();
            $baris = $lembar->array();
            $tanggal = fn (string $ymd) => ExcelDate::PHPToExcel(Carbon::parse($ymd));
            $riwayat = $this->barisLembar($lembar, 'riwayat');

            return count($export->sheets()) === 1
                && $lembar->title() === 'Laporan per Anggota'
                && $lembar->jumlahKolom() === 8
                && $baris[1] === ['KSP Karya Duta Utama']
                && $baris[2] === ['LAPORAN SIMPANAN PER ANGGOTA']
                && $baris[3] === ['Periode : 1 November 2026 s.d. 3 November 2026']
                && $this->barisLembar($lembar, 'pita-judul') === [["1. {$this->budi->no_anggota} — Budi   |   Bergabung: 1 November 2026   |   Status: Aktif"]]
                && $this->barisLembar($lembar, 'saldo') === [[100000.0, null, 50000.0, null, 200000.0, null, 350000.0, null]]
                && $this->barisLembar($lembar, 'pita-riwayat') === [["RIWAYAT TRANSAKSI — Budi ({$this->budi->no_anggota})"]]
                && count($riwayat) === 3
                && $riwayat[2] === [3, $tanggal('2026-11-02'), JenisSimpanan::SUKARELA, 'Setor', 200000.0, 200000.0, 'Admin Koperasi', 'Setoran awal']
                && $this->barisLembar($lembar, 'ringkasan') === [[350000.0, null, null, null, 0.0, null, null, null]]
                && end($baris) === ['Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi'];
        });
    }

    public function test_unduh_excel_semua_anggota(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', ['tanggal_mulai' => '2026-11-05']))->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Semua-Anggota-20261112.xlsx', function (LaporanSimpananExport $export) {
            $lembar = $export->lembar();

            return count($this->barisLembar($lembar, 'pita-judul')) === 3
                && count($this->barisLembar($lembar, 'pita-riwayat')) === 3
                && count($this->barisLembar($lembar, 'saldo')) === 3
                && count($this->barisLembar($lembar, 'ringkasan')) === 3
                && count($this->barisLembar($lembar, 'riwayat')) === 1
                && count($this->barisLembar($lembar, 'Tidak ada transaksi pada periode ini.')) === 2;
        });
    }

    public function test_unduh_excel_tanpa_data_menampilkan_keterangan(): void
    {
        Excel::fake();
        Anggota::query()->update(['status' => Anggota::STATUS_AKTIF]);

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', ['status_anggota' => 'nonaktif']))->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Semua-Anggota-20261112.xlsx', function (LaporanSimpananExport $export) {
            return count($this->barisLembar($export->lembar(), 'Tidak ada data sesuai filter.')) === 1;
        });
    }

    public function test_nik_tidak_muncul_di_pratinjau_pdf_dan_excel(): void
    {
        $html = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent();

        $laporan = app(LaporanService::class)->perAnggota([]);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';
        $pdf = view('laporan.pdf', $laporan)->render();
        $excel = json_encode((new LaporanSimpananExport($laporan))->lembar()->array());

        foreach (self::NIK as $nik) {
            $this->assertStringNotContainsString($nik, $html);
            $this->assertStringNotContainsString($nik, $pdf);
            $this->assertStringNotContainsString($nik, $excel);
        }
    }

    public function test_tidak_ada_n_plus_satu(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $hitungQuery = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->assertOk();
            $this->actingAs($this->admin)->get(route('laporan.anggota.pdf'))->assertOk();

            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $sebelum = $hitungQuery();

        $staffLain = User::factory()->create();
        foreach (Anggota::factory()->count(5)->create(['tanggal_bergabung' => '2026-11-01']) as $anggota) {
            $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
            $this->service->setor($anggota->id, $this->sukarela->id, 20000, '2026-11-02', $staffLain->id, null, 'Catatan');
        }

        $this->assertSame($sebelum, $hitungQuery());
    }

    public function test_tamu_tidak_bisa_membuka_maupun_mengunduh(): void
    {
        $this->get(route('laporan.anggota'))->assertRedirect(route('login'));
        $this->get(route('laporan.anggota.pdf', ['anggota_id' => $this->budi->id]))->assertRedirect(route('login'));
        $this->get(route('laporan.anggota.excel'))->assertRedirect(route('login'));
    }
}
