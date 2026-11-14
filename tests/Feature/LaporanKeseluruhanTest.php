<?php

namespace Tests\Feature;

use App\Exports\LaporanKeseluruhanExport;
use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use App\Services\LaporanService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

class LaporanKeseluruhanTest extends TestCase
{
    use RefreshDatabase;

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
     * Skenario uji akhir file 04: 7 transaksi, Andi nonaktif.
     */
    private function skenario(): void
    {
        $this->budi = Anggota::factory()->create(['nama' => 'Budi', 'nik' => '6471000000000001', 'tanggal_bergabung' => '2026-11-01']);
        $this->siti = Anggota::factory()->create(['nama' => 'Siti', 'nik' => '6471000000000002', 'tanggal_bergabung' => '2026-11-01']);
        $this->andi = Anggota::factory()->create(['nama' => 'Andi', 'nik' => '6471000000000003', 'tanggal_bergabung' => '2026-11-01']);

        $this->service->setor($this->budi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->wajib->id, 50000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->sukarela->id, 200000, '2026-11-02', $this->admin->id);
        $this->service->tarik($this->budi->id, 75000, '2026-11-05', $this->admin->id);

        $this->service->setor($this->siti->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->siti->id, $this->sukarela->id, 50000, '2026-11-02', $this->admin->id);

        $this->service->setor($this->andi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function pratinjau(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->get(route('laporan.keseluruhan', $query))
            ->assertOk()
            ->viewData('laporan');
    }

    /**
     * @param  array<string, mixed>  $laporan
     */
    private function saldoAnggota(array $laporan, string $nama): array
    {
        $baris = $laporan['saldo_anggota']->firstWhere('nama', $nama);

        return array_column($baris['saldo_per_jenis'], 'saldo', 'nama_jenis') + ['total' => $baris['total_saldo']];
    }

    // ---------------------------------------------------------------
    // Rekap simpanan (FR-15, BR-14), dipindah dari LaporanTest (revisi R2)
    // ---------------------------------------------------------------

    public function test_rekap_total_per_jenis_anggota_nonaktif_ikut_dan_grand_total(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.keseluruhan'));

        $response->assertOk();
        $response->assertSee('Rekap &amp; Laporan Keseluruhan', false);

        $rekap = $response->viewData('laporan')['ringkasan']->keyBy('nama_jenis');

        $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA], $rekap->keys()->all());
        // Transaksi Andi (nonaktif) ikut terhitung.
        $this->assertEquals(300000, $rekap[JenisSimpanan::POKOK]['total_setor']);
        $this->assertEquals(300000, $rekap[JenisSimpanan::POKOK]['saldo_akhir_periode']);
        $this->assertEquals(50000, $rekap[JenisSimpanan::WAJIB]['saldo_akhir_periode']);
        $this->assertEquals(250000, $rekap[JenisSimpanan::SUKARELA]['total_setor']);
        $this->assertEquals(75000, $rekap[JenisSimpanan::SUKARELA]['total_tarik']);
        $this->assertEquals(175000, $rekap[JenisSimpanan::SUKARELA]['saldo_akhir_periode']);

        $this->assertEquals(
            ['total_setor' => 600000.0, 'total_tarik' => 75000.0, 'saldo_akhir_periode' => 525000.0],
            $response->viewData('laporan')['total'],
        );
        $response->assertSee('Rp 525.000');
    }

    public function test_rekap_jenis_tanpa_transaksi_bernilai_nol(): void
    {
        TransaksiSimpanan::where('jenis_simpanan_id', $this->wajib->id)->delete();

        $laporan = $this->pratinjau();
        $rekap = $laporan['ringkasan']->keyBy('nama_jenis');

        $this->assertCount(3, $rekap);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_setor']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_tarik']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['saldo_akhir_periode']);
        $this->assertEquals(475000, $laporan['total']['saldo_akhir_periode']);
    }

    public function test_navigasi_ringkas_dengan_dropdown_transaksi_dan_laporan(): void
    {
        $html = $this->actingAs($this->admin)->get(route('laporan.keseluruhan'))->getContent();

        foreach (['Dashboard', 'Data Anggota', 'Setor', 'Tarik', 'Riwayat Transaksi', 'Rekap Keseluruhan', 'Laporan per Anggota', 'Ganti Password', 'Keluar'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertStringContainsString('data-menu-grup="Transaksi" data-menu-aktif="0"', $html);
        $this->assertStringContainsString('data-menu-grup="Laporan" data-menu-aktif="1"', $html);
        $this->assertStringNotContainsString('data-menu-grup="Transaksi" data-menu-aktif="1"', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('laporan.keseluruhan'), '/').'"[^>]*aria-current="page"/', $html);
        $this->assertStringNotContainsString('Rekap Simpanan', $html);

        $setor = $this->actingAs($this->admin)->get(route('transaksi.setor.create'))->getContent();
        $this->assertStringContainsString('data-menu-grup="Transaksi" data-menu-aktif="1"', $setor);
    }

    public function test_route_rekap_lama_sudah_dihapus(): void
    {
        $this->assertFalse(Route::has('laporan.rekap'));
        $this->actingAs($this->admin)->get('/laporan/rekap')->assertNotFound();
    }

    public function test_tanpa_filter_memuat_seluruh_data_dan_ringkasan_cocok_dengan_rekap(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.keseluruhan'));

        $response->assertOk();
        $laporan = $response->viewData('laporan');

        $this->assertSame([
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'jenis_simpanan_id' => null,
            'jenis_transaksi' => null,
            'status_anggota' => null,
        ], $response->viewData('filter'));

        $this->assertSame(7, $laporan['transaksi']->total());
        $this->assertSame('Seluruh periode', $laporan['periode']['label']);
        $this->assertSame('Jenis: Semua; Transaksi: Semua; Status: Semua', $laporan['filter_label']);
        $this->assertSame(['Andi', 'Budi', 'Siti'], $laporan['saldo_anggota']->pluck('nama')->sort()->values()->all());

        $rekap = app(SimpananService::class)->rekapPerJenis()
            ->map(fn ($r) => [$r['nama_jenis'], $r['total_setor'], $r['total_tarik'], $r['saldo']])
            ->all();
        $ringkasan = $laporan['ringkasan']
            ->map(fn ($r) => [$r['nama_jenis'], $r['total_setor'], $r['total_tarik'], $r['saldo_akhir_periode']])
            ->all();

        $this->assertSame($rekap, $ringkasan);
        $this->assertEquals(['total_setor' => 600000.0, 'total_tarik' => 75000.0, 'saldo_akhir_periode' => 525000.0], $laporan['total']);
    }

    public function test_filter_tanggal_inklusif(): void
    {
        $laporan = $this->pratinjau(['tanggal_mulai' => '2026-11-02', 'tanggal_selesai' => '2026-11-05']);

        $this->assertSame(3, $laporan['transaksi']->total());
        $this->assertSame(
            ['2026-11-02', '2026-11-02', '2026-11-05'],
            collect($laporan['transaksi']->items())->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d'))->all(),
        );
        $this->assertSame('2 November 2026 s.d. 5 November 2026', $laporan['periode']['label']);

        $ringkasan = $laporan['ringkasan']->keyBy('nama_jenis');
        $this->assertEquals(0, $ringkasan[JenisSimpanan::POKOK]['total_setor']);
        $this->assertEquals(300000, $ringkasan[JenisSimpanan::POKOK]['saldo_akhir_periode']);
        $this->assertEquals(250000, $ringkasan[JenisSimpanan::SUKARELA]['total_setor']);
        $this->assertEquals(75000, $ringkasan[JenisSimpanan::SUKARELA]['total_tarik']);
    }

    public function test_filter_jenis_simpanan(): void
    {
        $laporan = $this->pratinjau(['jenis_simpanan_id' => (string) $this->sukarela->id]);

        $this->assertSame(3, $laporan['transaksi']->total());
        $this->assertCount(1, $laporan['ringkasan']);
        $this->assertSame(JenisSimpanan::SUKARELA, $laporan['ringkasan'][0]['nama_jenis']);
        $this->assertEquals(175000, $laporan['total']['saldo_akhir_periode']);
        $this->assertSame('Jenis: Simpanan Sukarela; Transaksi: Semua; Status: Semua', $laporan['filter_label']);
        $this->assertSame([JenisSimpanan::SUKARELA => 125000.0, 'total' => 125000.0], $this->saldoAnggota($laporan, 'Budi'));
    }

    public function test_filter_jenis_transaksi(): void
    {
        $tarik = $this->pratinjau(['jenis_transaksi' => 'tarik']);

        $this->assertSame(1, $tarik['transaksi']->total());
        $this->assertSame('tarik', $tarik['transaksi']->items()[0]->jenis_transaksi);
        $this->assertSame('Jenis: Semua; Transaksi: Tarik; Status: Semua', $tarik['filter_label']);
        // Saldo tetap setor - tarik; filter jenis transaksi hanya untuk daftar transaksi.
        $this->assertEquals(525000, $tarik['total']['saldo_akhir_periode']);

        $this->assertSame(6, $this->pratinjau(['jenis_transaksi' => 'setor'])['transaksi']->total());
    }

    public function test_filter_status_anggota(): void
    {
        $nonaktif = $this->pratinjau(['status_anggota' => 'nonaktif']);

        $this->assertSame(['Andi'], $nonaktif['saldo_anggota']->pluck('nama')->all());
        $this->assertSame(1, $nonaktif['transaksi']->total());
        $this->assertEquals(100000, $nonaktif['total']['saldo_akhir_periode']);
        $this->assertSame('Jenis: Semua; Transaksi: Semua; Status: Nonaktif', $nonaktif['filter_label']);

        $aktif = $this->pratinjau(['status_anggota' => 'aktif']);

        $this->assertSame(['Budi', 'Siti'], $aktif['saldo_anggota']->pluck('nama')->sort()->values()->all());
        $this->assertSame(6, $aktif['transaksi']->total());
        $this->assertEquals(425000, $aktif['total']['saldo_akhir_periode']);
    }

    public function test_saldo_anggota_per_akhir_periode_mengabaikan_transaksi_setelah_tanggal_selesai(): void
    {
        $sampai = $this->pratinjau(['tanggal_selesai' => '2026-11-03']);

        $this->assertSame([
            JenisSimpanan::POKOK => 100000.0,
            JenisSimpanan::WAJIB => 50000.0,
            JenisSimpanan::SUKARELA => 200000.0,
            'total' => 350000.0,
        ], $this->saldoAnggota($sampai, 'Budi'));

        $penuh = $this->pratinjau();

        $this->assertSame(125000.0, $this->saldoAnggota($penuh, 'Budi')[JenisSimpanan::SUKARELA]);
        $this->assertSame(275000.0, $this->saldoAnggota($penuh, 'Budi')['total']);
        $this->assertSame(100000.0, $this->saldoAnggota($penuh, 'Andi')['total']);
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->from(route('laporan.keseluruhan'))
            ->get(route('laporan.keseluruhan', ['tanggal_mulai' => '2026-11-05', 'tanggal_selesai' => '2026-11-01']))
            ->assertRedirect(route('laporan.keseluruhan'))
            ->assertSessionHasErrors(['tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
    }

    public function test_nilai_filter_tidak_valid_ditolak(): void
    {
        $kasus = [
            'tanggal_mulai' => '05-11-2026',
            'tanggal_selesai' => 'kemarin',
            'jenis_simpanan_id' => '999',
            'jenis_transaksi' => 'pinjam',
            'status_anggota' => 'keluar',
        ];

        foreach ($kasus as $field => $nilai) {
            $this->actingAs($this->admin)
                ->from(route('laporan.keseluruhan'))
                ->get(route('laporan.keseluruhan', [$field => $nilai]))
                ->assertRedirect(route('laporan.keseluruhan'))
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($this->admin)
            ->get(route('laporan.keseluruhan.excel', ['jenis_transaksi' => 'pinjam']))
            ->assertSessionHasErrors('jenis_transaksi');
    }

    public function test_pratinjau_transaksi_dipaginasi_lima_puluh_ringkasan_tetap_penuh(): void
    {
        for ($i = 0; $i < 52; $i++) {
            $this->service->setor($this->siti->id, $this->wajib->id, 1000, '2026-11-10', $this->admin->id);
        }

        $laporan = $this->pratinjau();

        $this->assertSame(50, $laporan['transaksi']->perPage());
        $this->assertCount(50, $laporan['transaksi']->items());
        $this->assertSame(59, $laporan['transaksi']->total());
        $this->assertCount(3, $laporan['saldo_anggota']);
        $this->assertEquals(102000, $laporan['ringkasan']->firstWhere('nama_jenis', JenisSimpanan::WAJIB)['saldo_akhir_periode']);

        $this->assertCount(9, $this->pratinjau(['page' => '2'])['transaksi']->items());
    }

    public function test_pratinjau_menampilkan_tombol_unduh_dengan_filter_sama(): void
    {
        $query = ['tanggal_mulai' => '2026-11-01', 'jenis_transaksi' => 'setor'];

        $html = $this->actingAs($this->admin)->get(route('laporan.keseluruhan', $query))->getContent();

        $this->assertStringContainsString(e(route('laporan.keseluruhan.pdf', $query)), $html);
        $this->assertStringContainsString(e(route('laporan.keseluruhan.excel', $query)), $html);
        $this->assertStringContainsString('data-tombol-unduh="pdf"', $html);
        $this->assertStringContainsString('data-tombol-unduh="excel"', $html);
        $this->assertSame(1, substr_count($html, 'data-cegah-kirim-ganda'));
    }

    public function test_unduh_pdf_menghasilkan_lampiran_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.keseluruhan.pdf', ['status_anggota' => 'aktif']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $disposisi = $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment;', $disposisi);
        $this->assertStringContainsString('Laporan-Simpanan-Keseluruhan-20261112.pdf', $disposisi);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Baris data tabel sebuah sheet (meta jenis 'data'), tanpa kepala/header/catatan.
     *
     * @return array<int, array<int, mixed>>
     */
    private function barisData(LembarLaporan $lembar): array
    {
        $baris = $lembar->array();

        return array_values(array_map(
            fn ($nomor) => $baris[$nomor - 1],
            array_keys(array_filter($lembar->meta(), fn ($meta) => $meta['jenis'] === 'data')),
        ));
    }

    public function test_unduh_excel_tiga_sheet_dan_baris_transaksi_sesuai_filter(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)
            ->get(route('laporan.keseluruhan.excel', ['jenis_transaksi' => 'setor', 'status_anggota' => 'aktif']))
            ->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Keseluruhan-20261112.xlsx', function (LaporanKeseluruhanExport $export) {
            $sheets = $export->sheets();
            $judul = array_map(fn ($sheet) => $sheet->title(), $sheets);

            $ringkasan = $sheets[0]->array();
            $saldo = $sheets[1]->array();
            $transaksi = $this->barisData($sheets[2]);

            return $judul === ['Ringkasan', 'Saldo Anggota', 'Transaksi']
                && count($transaksi) === 5
                && $transaksi[0] === [1, ExcelDate::PHPToExcel(Carbon::parse('2026-11-01')), $this->budi->no_anggota, 'Budi', JenisSimpanan::POKOK, 'Setor', 100000.0, 100000.0, 'Admin Koperasi', '-']
                && count($this->barisData($sheets[1])) === 2
                && $saldo[7] === ['No', 'No. Anggota', 'Nama', 'Status', 'Tanggal Bergabung', JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA, 'Total Saldo']
                && $ringkasan[2] === ['LAPORAN SIMPANAN KESELURUHAN']
                && $ringkasan[4] === ['Filter : Jenis: Semua; Transaksi: Setor; Status: Aktif']
                && collect($this->barisData($sheets[0]))->last() === ['Total', 600000.0 - 100000.0, 75000.0, 425000.0];
        });
    }

    public function test_unduh_excel_tanpa_data_menampilkan_keterangan(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)
            ->get(route('laporan.keseluruhan.excel', ['tanggal_mulai' => '2026-11-10']))
            ->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Keseluruhan-20261112.xlsx', function (LaporanKeseluruhanExport $export) {
            return $export->sheets()[2]->array()[8] === ['Tidak ada data sesuai filter.'];
        });
    }

    public function test_nik_tidak_muncul_di_pratinjau_pdf_dan_excel(): void
    {
        $nik = ['6471000000000001', '6471000000000002', '6471000000000003'];

        $html = $this->actingAs($this->admin)->get(route('laporan.keseluruhan'))->getContent();

        $laporan = app(LaporanService::class)->keseluruhan([]);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';
        $pdf = view('laporan.pdf-keseluruhan', $laporan)->render();

        $excel = json_encode(array_map(fn ($sheet) => $sheet->array(), (new LaporanKeseluruhanExport($laporan))->sheets()));

        $this->assertStringContainsString('LAPORAN SIMPANAN KESELURUHAN', $pdf);
        $this->assertStringContainsString('Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $pdf);

        foreach ($nik as $n) {
            $this->assertStringNotContainsString($n, $html);
            $this->assertStringNotContainsString($n, $pdf);
            $this->assertStringNotContainsString($n, $excel);
        }
    }

    public function test_tamu_tidak_bisa_membuka_maupun_mengunduh(): void
    {
        $this->get(route('laporan.keseluruhan'))->assertRedirect(route('login'));
        $this->get(route('laporan.keseluruhan.pdf'))->assertRedirect(route('login'));
        $this->get(route('laporan.keseluruhan.excel'))->assertRedirect(route('login'));
    }

    public function test_tidak_ada_n_plus_satu(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $hitungQuery = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->admin)->get(route('laporan.keseluruhan'))->assertOk();
            $this->actingAs($this->admin)->get(route('laporan.keseluruhan.pdf'))->assertOk();

            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $sebelum = $hitungQuery();

        // Tambah anggota dan transaksi; jumlah query tidak boleh ikut bertambah.
        $lainnya = Anggota::factory()->count(5)->create(['tanggal_bergabung' => '2026-11-01']);
        foreach ($lainnya as $anggota) {
            $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
            $this->service->setor($anggota->id, $this->sukarela->id, 20000, '2026-11-02', $this->admin->id);
        }
        $staffLain = User::factory()->create();
        $this->service->setor($this->budi->id, $this->wajib->id, 10000, '2026-11-06', $staffLain->id);

        $this->assertSame($sebelum, $hitungQuery());
    }
}
