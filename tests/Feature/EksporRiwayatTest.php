<?php

namespace Tests\Feature;

use App\Exports\RiwayatTransaksiExport;
use App\Http\Controllers\TransaksiSimpananController;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Ekspor Riwayat transaksi ke PDF dan Excel (F5 H.5): filter sama dengan halaman, urut lama → baru,
 * tanpa NIK, penanda nonaktif, ringkasan, batas 2.000 baris untuk PDF, dan tombol Unduh di halaman.
 */
class EksporRiwayatTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = ['6471000000000101', '6471000000000102'];

    private User $admin;

    private Anggota $budi;

    private Anggota $andi;

    private JenisSimpanan $pokok;

    private JenisSimpanan $sukarela;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 14:30:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);
        $service = app(SimpananService::class);
        $this->pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->firstOrFail();
        $this->sukarela = JenisSimpanan::sukarela();

        $this->budi = Anggota::factory()->create(['nama' => 'Budi Santoso', 'nik' => self::NIK[0], 'tanggal_bergabung' => '2026-10-01']);
        $this->andi = Anggota::factory()->create(['nama' => 'Andi Wijaya', 'nik' => self::NIK[1], 'tanggal_bergabung' => '2026-10-01']);

        // Urutan id sengaja tidak sama dengan urutan tanggal (jenis berbeda boleh dicatat mundur).
        $service->setor($this->budi->id, $this->sukarela->id, 200000, '2026-11-05', $this->admin->id, null, 'Tabungan');
        $service->setor($this->budi->id, $this->pokok->id, 100000, '2026-11-02', $this->admin->id);
        $service->tarik($this->budi->id, 200000, '2026-11-08', $this->admin->id); // saldo sukarela menjadi 0
        $service->setor($this->andi->id, $this->pokok->id, 100000, '2026-11-03', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Unduh PDF lewat route, tangkap data view-nya, lalu render HTML-nya untuk diperiksa.
     *
     * @param  array<string, string>  $query
     * @return array{data: array<string, mixed>, html: string}
     */
    private function pdf(array $query = []): array
    {
        $data = null;
        View::composer('transaksi.pdf-riwayat', function ($view) use (&$data) {
            $data ??= $view->getData();
        });

        $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat.pdf', $query));
        $response->assertOk();
        $this->assertSame('attachment; filename=riwayat-transaksi-2026-11-12.pdf', $response->headers->get('content-disposition'));

        return ['data' => $data, 'html' => view('transaksi.pdf-riwayat', $data)->render()];
    }

    /**
     * Unduh Excel lewat route dan baca file XLSX aslinya.
     *
     * @param  array<string, string>  $query
     */
    private function excel(array $query = []): Worksheet
    {
        $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat.excel', $query));
        $response->assertOk();
        $this->assertSame('attachment; filename=riwayat-transaksi-2026-11-12.xlsx', $response->headers->get('content-disposition'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    /**
     * Id transaksi halaman Riwayat untuk filter yang sama, diurutkan lama → baru (pembanding ekspor).
     *
     * @param  array<string, string>  $query
     * @return array<int, int>
     */
    private function idHalaman(array $query): array
    {
        $riwayat = $this->actingAs($this->admin)->get(route('transaksi.riwayat', $query))->viewData('riwayat');

        return collect($riwayat->items())
            ->sortBy([['tanggal_transaksi', 'asc'], ['id', 'asc']])
            ->pluck('id')->values()->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function barisData(Worksheet $sheet): array
    {
        $baris = [];
        for ($nomor = RiwayatTransaksiExport::BARIS_HEADER + 1; $nomor <= $sheet->getHighestRow(); $nomor++) {
            if ($sheet->getCell("B{$nomor}")->getValue() === null) {
                break;
            }
            $baris[$nomor] = $sheet->rangeToArray("A{$nomor}:I{$nomor}", null, false, false)[0];
        }

        return $baris;
    }

    private function buatBanyakTransaksi(int $jumlah): void
    {
        $sekarang = now();
        foreach (array_chunk(range(1, $jumlah), 500) as $potongan) {
            DB::table('transaksi_simpanan')->insert(array_map(fn (int $i) => [
                'anggota_id' => $this->budi->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'staff_id' => $this->admin->id,
                'jenis_transaksi' => 'setor',
                'jumlah' => 1000,
                'tanggal_transaksi' => '2026-11-10',
                'saldo_akhir' => 100000 + $i * 1000,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ], $potongan));
        }
    }

    public function test_kedua_route_ekspor_riwayat_butuh_login(): void
    {
        $this->get(route('transaksi.riwayat.pdf'))->assertRedirect(route('login'));
        $this->get(route('transaksi.riwayat.excel'))->assertRedirect(route('login'));
        $this->assertSame('transaksi/riwayat/pdf', app('router')->getRoutes()->getByName('transaksi.riwayat.pdf')->uri());
        $this->assertSame('transaksi/riwayat/excel', app('router')->getRoutes()->getByName('transaksi.riwayat.excel')->uri());
    }

    public function test_pdf_urut_lama_ke_baru_tanpa_nik_penanda_nonaktif_kepala_dan_ringkasan(): void
    {
        ['data' => $data, 'html' => $html] = $this->pdf();

        $this->assertSame(['2026-11-02', '2026-11-03', '2026-11-05', '2026-11-08'], $data['transaksi']->map(fn ($t) => $t->tanggal_transaksi->toDateString())->all());
        $this->assertSame(TransaksiSimpanan::orderBy('tanggal_transaksi')->orderBy('id')->pluck('id')->all(), $data['transaksi']->pluck('id')->all());

        foreach (self::NIK as $nik) {
            $this->assertStringNotContainsString($nik, $html);
        }
        $this->assertMatchesRegularExpression('/<td>Andi Wijaya <span class="lap-merah" data-penanda-nonaktif>\(Nonaktif\)<\/span><\/td>/', $html);
        $this->assertStringNotContainsString('Budi Santoso <span', $html);

        // Kolom berurutan, warna jenis transaksi seperti PDF laporan.
        $this->assertMatchesRegularExpression('/<th>Tanggal<\/th>\s*<th>No\. Anggota<\/th>\s*<th>Nama Anggota<\/th>\s*<th>Jenis Transaksi<\/th>\s*<th>Jenis Simpanan<\/th>\s*<th>Jumlah<\/th>\s*<th>Saldo Setelah Transaksi<\/th>\s*<th>Dicatat Oleh<\/th>\s*<th>Catatan<\/th>/', $html);
        $this->assertStringContainsString('<td class="lap-hijau">Setor</td>', $html);
        $this->assertStringContainsString('<td class="lap-merah">Tarik</td>', $html);

        // Kepala: nama koperasi, judul, periode, filter, waktu unduh.
        $this->assertStringContainsString('KSP Karya Duta Utama', $html);
        $this->assertStringContainsString('<p class="lap-judul">Riwayat Transaksi Simpanan</p>', $html);
        $this->assertStringContainsString('Periode : Semua waktu', $html);
        $this->assertStringContainsString('Filter : Anggota: Semua; Transaksi: Semua; Jenis: Semua', $html);
        $this->assertStringContainsString('Diunduh pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $html);

        // Ringkasan: 4 transaksi, setor 400.000, tarik 200.000, bersih +200.000.
        $this->assertStringContainsString('data-ringkasan="jumlah">4</td>', $html);
        $this->assertStringContainsString('data-ringkasan="total_setor">Rp 400.000</td>', $html);
        $this->assertStringContainsString('data-ringkasan="total_tarik">Rp 200.000</td>', $html);
        $this->assertStringContainsString('data-ringkasan="bersih"><strong>+Rp 200.000</strong>', $html);
    }

    public function test_pdf_dan_excel_membawa_filter_yang_sama_dengan_halaman(): void
    {
        foreach ([
            ['jenis_transaksi' => 'tarik'],
            ['anggota_id' => (string) $this->andi->id],
            ['cari' => 'budi', 'jenis_simpanan_id' => (string) $this->pokok->id],
            ['rentang' => 'kustom', 'dari' => '2026-11-03', 'sampai' => '2026-11-06'],
            ['rentang' => 'bulan_ini', 'jenis_transaksi' => 'setor'],
        ] as $query) {
            $harapan = $this->idHalaman($query);
            $this->assertNotEmpty($harapan, json_encode($query));

            $this->assertSame($harapan, $this->pdf($query)['data']['transaksi']->pluck('id')->all(), 'PDF '.json_encode($query));

            $baris = $this->barisData($this->excel($query));
            $this->assertCount(count($harapan), $baris, 'Excel '.json_encode($query));
            $harapanExcel = TransaksiSimpanan::with('anggota')->findMany($harapan)->sortBy(fn ($t) => array_search($t->id, $harapan))
                ->map(fn ($t) => [$t->anggota->no_anggota, (float) $t->jumlah])->values()->all();
            $this->assertSame($harapanExcel, array_values(array_map(fn ($sel) => [$sel[1], (float) $sel[5]], $baris)), 'Excel '.json_encode($query));
        }

        // Kepala mencerminkan filter aktif dan label periode dari helper yang sama dengan halaman.
        $html = $this->pdf(['anggota_id' => (string) $this->andi->id, 'jenis_transaksi' => 'setor', 'cari' => 'wij', 'rentang' => 'kustom', 'dari' => '2026-11-01', 'sampai' => '2026-11-10'])['html'];
        $this->assertStringContainsString('Periode : 1 Nov 2026 – 10 Nov 2026', $html);
        $this->assertStringContainsString("Filter : Anggota: {$this->andi->no_anggota} - Andi Wijaya (Nonaktif); Transaksi: Setor; Jenis: Semua; Cari: &quot;wij&quot;", $html);
        $this->assertSame('Bulan ini', TransaksiSimpananController::labelPeriodeRiwayat(['rentang' => 'bulan_ini']));
        $this->assertSame('Semua waktu', TransaksiSimpananController::labelPeriodeRiwayat(['rentang' => null]));
    }

    public function test_excel_kepala_kolom_urutan_nol_angka_penanda_dan_ringkasan(): void
    {
        $sheet = $this->excel();

        $this->assertSame('Riwayat Transaksi', $sheet->getTitle());
        $this->assertSame('KSP Karya Duta Utama', $sheet->getCell('A2')->getValue());
        $this->assertSame('Riwayat Transaksi Simpanan', $sheet->getCell('A3')->getValue());
        $this->assertSame('Periode : Semua waktu', $sheet->getCell('A4')->getValue());
        $this->assertSame('Filter : Anggota: Semua; Transaksi: Semua; Jenis: Semua', $sheet->getCell('A5')->getValue());
        $this->assertSame('Diunduh pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $sheet->getCell('A6')->getValue());
        $this->assertSame(RiwayatTransaksiExport::KOLOM, $sheet->rangeToArray('A8:I8')[0]);

        $baris = array_values($this->barisData($sheet));
        $this->assertCount(4, $baris);
        // Tanggal = tanggal Excel asli, urut lama → baru.
        $this->assertSame(['2026-11-02', '2026-11-03', '2026-11-05', '2026-11-08'], array_map(fn ($sel) => ExcelDate::excelToDateTimeObject($sel[0])->format('Y-m-d'), $baris));
        $this->assertSame('Andi Wijaya (Nonaktif)', $baris[1][2]);
        $this->assertSame('Budi Santoso', $baris[0][2]);
        $this->assertSame(['Setor', 'Setor', 'Setor', 'Tarik'], array_column($baris, 3));
        $this->assertSame('-', $baris[0][8]);
        $this->assertSame('Tabungan', $baris[2][8]);

        // Saldo setelah tarik seluruh saldo = 0, tersimpan sebagai angka 0 (bukan sel kosong).
        $sel = $sheet->getCell('G12');
        $this->assertSame(DataType::TYPE_NUMERIC, $sel->getDataType());
        $this->assertEquals(0, $sel->getValue());
        $this->assertSame('"Rp" #,##0', $sheet->getStyle('F9')->getNumberFormat()->getFormatCode());

        // Tanpa NIK di seluruh sheet.
        $semua = json_encode($sheet->toArray());
        foreach (self::NIK as $nik) {
            $this->assertStringNotContainsString($nik, $semua);
        }

        // Ringkasan di akhir (baris 14–17): jumlah, total setor, total tarik, bersih bertanda.
        $this->assertSame(['Jumlah transaksi', 4], [$sheet->getCell('A14')->getValue(), $sheet->getCell('F14')->getValue()]);
        $this->assertEquals(400000, $sheet->getCell('F15')->getValue());
        $this->assertEquals(200000, $sheet->getCell('F16')->getValue());
        $this->assertSame('Bersih', $sheet->getCell('A17')->getValue());
        $this->assertEquals(200000, $sheet->getCell('F17')->getValue());
        $this->assertSame('"+Rp" #,##0;"-Rp" #,##0;"Rp" 0', $sheet->getStyle('F17')->getNumberFormat()->getFormatCode());

        // Hasil kosong: baris keterangan, ringkasan 0 tetap angka.
        $kosong = $this->excel(['cari' => 'tidak-ada']);
        $this->assertSame(RiwayatTransaksiExport::KOSONG, $kosong->getCell('A9')->getValue());
        $this->assertSame(0, $kosong->getCell('F11')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $kosong->getCell('F12')->getDataType());
    }

    public function test_pdf_dibatasi_lima_ratus_baris_sedangkan_excel_memuat_semua(): void
    {
        $this->assertSame(500, TransaksiSimpananController::BATAS_PDF_RIWAYAT);
        $this->buatBanyakTransaksi(497); // total 4 + 497 = 501, satu di atas batas

        // Filter yang dipersempit (1 baris tarik) tetap terunduh sebagai PDF.
        $this->actingAs($this->admin)
            ->get(route('transaksi.riwayat.pdf', ['jenis_transaksi' => 'tarik']))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('transaksi.riwayat.pdf', ['rentang' => 'bulan_ini']))
            ->assertRedirect(route('transaksi.riwayat', ['rentang' => 'bulan_ini']))
            ->assertSessionHas('info', 'Data terlalu banyak untuk PDF. Persempit filter atau unduh Excel.');

        // Halaman Riwayat: item PDF nonaktif dengan title; Excel tetap tautan.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent());
        $pdf = $xpath->query('//*[@data-unduh-riwayat]//*[@data-unduh="pdf"]')->item(0);
        $this->assertSame('span', $pdf->nodeName);
        $this->assertSame('true', $pdf->getAttribute('aria-disabled'));
        $this->assertSame('Lebih dari 500 transaksi, pakai Excel atau persempit filter.', $pdf->getAttribute('title'));
        $this->assertSame('a', $xpath->query('//*[@data-unduh-riwayat]//*[@data-unduh="excel"]')->item(0)->nodeName);

        // Excel tanpa batas: semua 501 baris ada.
        $this->assertCount(501, $this->barisData($this->excel()));

        // Tepat 500 baris: PDF tidak ditolak, item PDF di halaman berupa tautan.
        TransaksiSimpanan::query()->latest('id')->first()->delete();
        $this->assertSame(500, TransaksiSimpanan::count());
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent());
        $this->assertSame('a', $xpath->query('//*[@data-unduh-riwayat]//*[@data-unduh="pdf"]')->item(0)->nodeName);
    }

    public function test_tombol_unduh_riwayat_membawa_filter_dan_tersembunyi_saat_kosong(): void
    {
        $query = ['jenis_transaksi' => 'setor', 'rentang' => 'kustom', 'dari' => '2026-11-01', 'sampai' => '2026-11-10'];
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', $query + ['page' => '1']))->getContent());

        $menu = $xpath->query('//*[@data-unduh-riwayat]');
        $this->assertSame(1, $menu->length);
        $this->assertSame('Unduh', trim($xpath->query('.//button', $menu->item(0))->item(0)->textContent));

        foreach (['excel' => 'transaksi.riwayat.excel', 'pdf' => 'transaksi.riwayat.pdf'] as $jenis => $nama) {
            $href = $xpath->query('//*[@data-unduh-riwayat]//a[@data-unduh="'.$jenis.'"]')->item(0)->getAttribute('href');
            $this->assertSame(route($nama), strtok($href, '?'));
            parse_str((string) parse_url($href, PHP_URL_QUERY), $parameter);
            ksort($parameter);
            $harapan = $query;
            ksort($harapan);
            $this->assertSame($harapan, $parameter, $jenis);
        }

        // Di kepala halaman, di kiri tombol Catat setoran.
        $aksi = $xpath->query('//*[@data-unduh-riwayat]/following-sibling::a[contains(@href, "'.route('transaksi.setor.create').'")]');
        $this->assertSame(1, $aksi->length);

        // Hasil filter kosong dan Riwayat tanpa transaksi: tombol tidak tampil.
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['cari' => 'tidak-ada']))
            ->assertDontSee('data-unduh-riwayat', false);
        TransaksiSimpanan::query()->delete();
        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertDontSee('data-unduh-riwayat', false);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
