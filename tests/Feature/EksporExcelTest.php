<?php

namespace Tests\Feature;

use App\Exports\LaporanKeseluruhanExport;
use App\Exports\LaporanSimpananExport;
use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\LaporanService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Revisi R3–R4: file Excel sungguhan (bukan Excel::fake) dibuat ke storage
 * sementara lalu dibaca dengan PhpSpreadsheet untuk memeriksa gaya.
 */
class EksporExcelTest extends TestCase
{
    use RefreshDatabase;

    /** Baris header tabel: 6 baris kepala + 1 baris jarak. */
    private const BARIS_HEADER = 8;

    private User $admin;

    private Anggota $budi;

    private Anggota $siti;

    private Anggota $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 14:30:00');
        Storage::fake('local');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);
        $service = app(SimpananService::class);

        $pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->value('id');
        $wajib = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->value('id');
        $sukarela = JenisSimpanan::sukarela()->id;

        $this->budi = Anggota::factory()->create(['nama' => 'Budi', 'tanggal_bergabung' => '2026-11-01']);
        $this->siti = Anggota::factory()->create(['nama' => 'Siti', 'tanggal_bergabung' => '2026-11-01']);
        $this->andi = Anggota::factory()->create(['nama' => 'Andi', 'tanggal_bergabung' => '2026-11-01']);

        $service->setor($this->budi->id, $pokok, 100000, '2026-11-01', $this->admin->id);
        $service->setor($this->budi->id, $wajib, 50000, '2026-11-01', $this->admin->id);
        $service->setor($this->budi->id, $sukarela, 200000, '2026-11-02', $this->admin->id, null, 'Setoran awal untuk tabungan pendidikan anak pertama');
        $service->tarik($this->budi->id, 75000, '2026-11-05', $this->admin->id);
        $service->setor($this->siti->id, $pokok, 100000, '2026-11-01', $this->admin->id); // Siti: Wajib & Sukarela 0
        $service->setor($this->andi->id, $pokok, 100000, '2026-11-01', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function simpanLaluBaca(object $export, string $nama): Spreadsheet
    {
        Excel::store($export, $nama, 'local');

        return IOFactory::load(Storage::disk('local')->path($nama));
    }

    private function perAnggota(array $filter = []): Spreadsheet
    {
        $laporan = app(LaporanService::class)->perAnggota($filter) + ['dicetak_oleh' => 'Admin Koperasi'];

        return $this->simpanLaluBaca(new LaporanSimpananExport($laporan), 'per-anggota.xlsx');
    }

    private function keseluruhan(): Spreadsheet
    {
        $laporan = app(LaporanService::class)->keseluruhan([]) + ['dicetak_oleh' => 'Admin Koperasi'];

        return $this->simpanLaluBaca(new LaporanKeseluruhanExport($laporan), 'keseluruhan.xlsx');
    }

    /**
     * Nomor baris pertama yang kolom A-nya berisi $teks.
     */
    private function cariBaris(Worksheet $sheet, string $teks, int $mulai = 1): int
    {
        for ($baris = $mulai; $baris <= $sheet->getHighestRow(); $baris++) {
            if ($sheet->getCell("A{$baris}")->getValue() === $teks) {
                return $baris;
            }
        }

        $this->fail("Baris berisi '{$teks}' tidak ditemukan.");
    }

    private function assertHeaderTabel(Worksheet $sheet, string $rentang): void
    {
        $gaya = $sheet->getStyle($rentang);

        $this->assertTrue($gaya->getFont()->getBold(), "Header {$rentang} harus bold.");
        $this->assertSame(Fill::FILL_SOLID, $gaya->getFill()->getFillType());
        $this->assertSame(LembarLaporan::ABU, $gaya->getFill()->getStartColor()->getRGB());
        $this->assertSame(Alignment::HORIZONTAL_CENTER, $gaya->getAlignment()->getHorizontal());
        $this->assertBergaris($sheet, $rentang);
    }

    private function assertBergaris(Worksheet $sheet, string $sel): void
    {
        $garis = $sheet->getStyle($sel)->getBorders();

        foreach (['getTop', 'getBottom', 'getLeft', 'getRight'] as $sisi) {
            $this->assertSame(Border::BORDER_THIN, $garis->{$sisi}()->getBorderStyle(), "Sel {$sel} harus bergaris tipis ({$sisi}).");
        }
    }

    private function assertRupiah(Worksheet $sheet, string $sel, float $nilai): void
    {
        $cell = $sheet->getCell($sel);

        $this->assertSame(DataType::TYPE_NUMERIC, $cell->getDataType(), "Sel {$sel} harus angka.");
        $this->assertEquals($nilai, $cell->getValue());
        $this->assertSame(LembarLaporan::FORMAT_RUPIAH, $sheet->getStyle($sel)->getNumberFormat()->getFormatCode());
    }

    private function assertTanggal(Worksheet $sheet, string $sel, string $ymd): void
    {
        $cell = $sheet->getCell($sel);

        $this->assertSame(DataType::TYPE_NUMERIC, $cell->getDataType(), "Sel {$sel} harus nilai tanggal Excel.");
        $this->assertTrue(ExcelDate::isDateTime($cell), "Sel {$sel} harus berformat tanggal.");
        $this->assertSame(LembarLaporan::FORMAT_TANGGAL, $sheet->getStyle($sel)->getNumberFormat()->getFormatCode());
        $this->assertSame($ymd, ExcelDate::excelToDateTimeObject($cell->getValue())->format('Y-m-d'));
    }

    private function assertWarna(Worksheet $sheet, string $sel, string $warna): void
    {
        $this->assertSame($warna, $sheet->getStyle($sel)->getFont()->getColor()->getRGB(), "Warna font {$sel}.");
    }

    /**
     * @param  array{0: int, 1: int}  $barisBerulang  print titles yang diharapkan
     */
    private function assertPengaturanCetak(Worksheet $sheet, array $barisBerulang = [self::BARIS_HEADER, self::BARIS_HEADER]): void
    {
        $halaman = $sheet->getPageSetup();

        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $halaman->getOrientation());
        $this->assertSame(PageSetup::PAPERSIZE_A4, $halaman->getPaperSize());
        $this->assertSame(1, $halaman->getFitToWidth());
        $this->assertSame(0, $halaman->getFitToHeight());
        $this->assertEquals($barisBerulang, $halaman->getRowsToRepeatAtTop());
        $this->assertStringContainsString('Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $sheet->getHeaderFooter()->getOddFooter());
    }

    private function assertKepala(Worksheet $sheet, string $judul, string $kolomAkhir): void
    {
        $this->assertSame('KSP Karya Duta Utama', $sheet->getCell('A2')->getValue());
        $this->assertSame($judul, $sheet->getCell('A3')->getValue());
        $this->assertStringStartsWith('Periode : ', $sheet->getCell('A4')->getValue());
        $this->assertStringStartsWith('Filter : ', $sheet->getCell('A5')->getValue());
        $this->assertSame(LembarLaporan::KETERANGAN, $sheet->getCell('A6')->getValue());

        foreach (range(1, 6) as $baris) {
            $this->assertArrayHasKey("A{$baris}:{$kolomAkhir}{$baris}", $sheet->getMergeCells(), "Kepala baris {$baris} harus di-merge selebar tabel.");
            $this->assertSame(Alignment::HORIZONTAL_CENTER, $sheet->getStyle("A{$baris}")->getAlignment()->getHorizontal());
        }

        $this->assertTrue($sheet->getStyle('A3')->getFont()->getBold());
        $this->assertEquals(16, $sheet->getStyle('A3')->getFont()->getSize());
    }

    // ---------------------------------------------------------------
    // Laporan per anggota (tata letak blok, revisi R6)
    // ---------------------------------------------------------------

    private function teks(Worksheet $sheet, string $sel): string
    {
        $nilai = $sheet->getCell($sel)->getValue();

        return $nilai instanceof RichText ? $nilai->getPlainText() : (string) $nilai;
    }

    /**
     * Nomor baris pertama (mulai $mulai) yang teks kolom A-nya diawali $awalan.
     */
    private function barisDiawali(Worksheet $sheet, string $awalan, int $mulai = 1): int
    {
        for ($baris = $mulai; $baris <= $sheet->getHighestRow(); $baris++) {
            if (str_starts_with($this->teks($sheet, "A{$baris}"), $awalan)) {
                return $baris;
            }
        }

        $this->fail("Baris diawali '{$awalan}' tidak ditemukan.");
    }

    private function lembarPerAnggota(array $filter = []): Worksheet
    {
        $sheet = $this->perAnggota($filter)->getSheetByName('Laporan per Anggota');
        $this->assertNotNull($sheet);

        return $sheet;
    }

    public function test_excel_per_anggota_kepala_dan_pengaturan_cetak(): void
    {
        $sheet = $this->lembarPerAnggota();

        $this->assertKepala($sheet, 'LAPORAN SIMPANAN PER ANGGOTA', 'H');
        // Tanpa header tabel bersama: kepala laporan (baris 1–6) diulang di setiap halaman cetak.
        $this->assertPengaturanCetak($sheet, [1, 6]);
    }

    public function test_excel_per_anggota_satu_blok_per_anggota_dengan_merge(): void
    {
        $sheet = $this->lembarPerAnggota();
        $mulai = 8;

        foreach ([[1, $this->budi, 4], [2, $this->siti, 1], [3, $this->andi, 1]] as [$nomor, $anggota, $jumlahTransaksi]) {
            // 1. Pita judul: merge A..H, abu-abu gelap, bold.
            $pita = $this->barisDiawali($sheet, "{$nomor}. {$anggota->no_anggota} — {$anggota->nama}", $mulai);
            $this->assertArrayHasKey("A{$pita}:H{$pita}", $sheet->getMergeCells(), "Pita judul {$anggota->nama} harus di-merge.");
            $this->assertSame(LembarLaporan::ABU_GELAP, $sheet->getStyle("A{$pita}")->getFill()->getStartColor()->getRGB());
            $this->assertTrue($sheet->getStyle("A{$pita}")->getFont()->getBold());
            $this->assertStringContainsString('Bergabung: 1 November 2026', $this->teks($sheet, "A{$pita}"));

            // 2. Tabel saldo: 4 kolom sama lebar (pasangan merge), header abu-abu, nilai bold Rupiah.
            $headerSaldo = $pita + ($anggota->is($this->andi) ? 2 : 1); // Andi: + keterangan saldo nonaktif
            foreach (['A:B', 'C:D', 'E:F', 'G:H'] as $pasangan) {
                [$dari, $sampai] = explode(':', $pasangan);
                $this->assertArrayHasKey("{$dari}{$headerSaldo}:{$sampai}{$headerSaldo}", $sheet->getMergeCells());
                $this->assertArrayHasKey("{$dari}".($headerSaldo + 1).":{$sampai}".($headerSaldo + 1), $sheet->getMergeCells());
            }
            $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA, 'Total Saldo'], array_map(
                fn ($kolom) => $sheet->getCell("{$kolom}{$headerSaldo}")->getValue(),
                ['A', 'C', 'E', 'G'],
            ));
            $this->assertHeaderTabel($sheet, "A{$headerSaldo}:H{$headerSaldo}");
            $this->assertTrue($sheet->getStyle('A'.($headerSaldo + 1))->getFont()->getBold());
            $this->assertSame(LembarLaporan::FORMAT_RUPIAH, $sheet->getStyle('G'.($headerSaldo + 1))->getNumberFormat()->getFormatCode());

            // 3. Pita riwayat: merge A..H, rata tengah, abu-abu muda.
            $pitaRiwayat = $headerSaldo + 2;
            $this->assertSame("RIWAYAT TRANSAKSI — {$anggota->nama} ({$anggota->no_anggota})", $this->teks($sheet, "A{$pitaRiwayat}"));
            $this->assertArrayHasKey("A{$pitaRiwayat}:H{$pitaRiwayat}", $sheet->getMergeCells(), "Pita riwayat {$anggota->nama} harus di-merge.");
            $this->assertSame(Alignment::HORIZONTAL_CENTER, $sheet->getStyle("A{$pitaRiwayat}")->getAlignment()->getHorizontal());
            $this->assertSame(LembarLaporan::ABU_PITA, $sheet->getStyle("A{$pitaRiwayat}")->getFill()->getStartColor()->getRGB());

            // 4. Tabel riwayat dengan header sendiri (A..H, tidak ada merge).
            $headerRiwayat = $pitaRiwayat + 1;
            $this->assertSame(['No', 'Tanggal', 'Jenis Simpanan', 'Jenis Transaksi', 'Jumlah', 'Saldo Akhir', 'Dicatat Oleh', 'Catatan'], array_map(
                fn ($kolom) => $sheet->getCell("{$kolom}{$headerRiwayat}")->getValue(),
                range('A', 'H'),
            ));
            $this->assertHeaderTabel($sheet, "A{$headerRiwayat}:H{$headerRiwayat}");
            foreach (range(1, $jumlahTransaksi) as $j) {
                $this->assertSame($j, $sheet->getCell('A'.($headerRiwayat + $j))->getValue());
                $this->assertBergaris($sheet, 'H'.($headerRiwayat + $j));
            }

            // 5. Ringkasan periode: bold, tetap angka, label lewat format tampilan.
            $ringkasan = $headerRiwayat + $jumlahTransaksi + 1;
            $this->assertArrayHasKey("A{$ringkasan}:D{$ringkasan}", $sheet->getMergeCells());
            $this->assertArrayHasKey("E{$ringkasan}:H{$ringkasan}", $sheet->getMergeCells());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell("A{$ringkasan}")->getDataType());
            $this->assertStringStartsWith('Total setor periode: Rp', $sheet->getCell("A{$ringkasan}")->getFormattedValue());
            $this->assertStringStartsWith('Total tarik periode: Rp', $sheet->getCell("E{$ringkasan}")->getFormattedValue());
            $this->assertTrue($sheet->getStyle("A{$ringkasan}")->getFont()->getBold());

            // 6. Satu baris kosong pemisah.
            $this->assertNull($sheet->getCell('A'.($ringkasan + 1))->getValue());
            $mulai = $ringkasan + 1;
        }

        // Nilai ringkasan Budi (seluruh periode): setor 350.000, tarik 75.000.
        $budi = $this->barisDiawali($sheet, "1. {$this->budi->no_anggota}");
        $ringkasanBudi = $budi + 1 + 2 + 1 + 4 + 1;
        $this->assertEquals(350000, $sheet->getCell("A{$ringkasanBudi}")->getValue());
        $this->assertEquals(75000, $sheet->getCell("E{$ringkasanBudi}")->getValue());
    }

    public function test_excel_per_anggota_riwayat_tanggal_angka_dan_warna(): void
    {
        $sheet = $this->lembarPerAnggota();
        $header = $this->barisDiawali($sheet, 'RIWAYAT TRANSAKSI — Budi') + 1;

        // Baris saldo Budi: nilai Rupiah di kolom awal pasangan.
        $nilaiSaldo = $header - 2;
        $this->assertRupiah($sheet, "A{$nilaiSaldo}", 100000);
        $this->assertRupiah($sheet, "E{$nilaiSaldo}", 125000);
        $this->assertRupiah($sheet, "G{$nilaiSaldo}", 275000);

        $this->assertTanggal($sheet, 'B'.($header + 1), '2026-11-01');
        $this->assertSame('Setor', $sheet->getCell('D'.($header + 1))->getValue());
        $this->assertWarna($sheet, 'D'.($header + 1), LembarLaporan::HIJAU);
        $this->assertRupiah($sheet, 'E'.($header + 1), 100000);
        $this->assertSame('Admin Koperasi', $sheet->getCell('G'.($header + 1))->getValue());

        $this->assertSame('Setoran awal untuk tabungan pendidikan anak pertama', $sheet->getCell('H'.($header + 3))->getValue());
        $this->assertTrue($sheet->getStyle('H'.($header + 3))->getAlignment()->getWrapText());

        $this->assertSame('Tarik', $sheet->getCell('D'.($header + 4))->getValue());
        $this->assertWarna($sheet, 'D'.($header + 4), LembarLaporan::MERAH);
    }

    public function test_excel_nilai_nol_tersimpan_sebagai_angka_nol_berformat_rupiah(): void
    {
        $sheet = $this->lembarPerAnggota();
        $nilaiSaldoSiti = $this->barisDiawali($sheet, "2. {$this->siti->no_anggota} — Siti") + 2;

        $this->assertRupiah($sheet, "C{$nilaiSaldoSiti}", 0);  // Wajib 0
        $this->assertRupiah($sheet, "E{$nilaiSaldoSiti}", 0);  // Sukarela 0
        $this->assertSame(0, (int) $sheet->getCell("C{$nilaiSaldoSiti}")->getValue());
        $this->assertSame('Rp 0', $sheet->getCell("C{$nilaiSaldoSiti}")->getFormattedValue());

        // Ringkasan tarik Siti 0 tetap tampil.
        $ringkasanSiti = $nilaiSaldoSiti + 1 + 1 + 1 + 1;
        $this->assertSame('Total tarik periode: Rp 0', $sheet->getCell("E{$ringkasanSiti}")->getFormattedValue());
    }

    public function test_excel_per_anggota_tanpa_transaksi_di_merge_selebar_tabel(): void
    {
        $sheet = $this->lembarPerAnggota(['tanggal_mulai' => '2026-11-05']);

        $baris = $this->barisDiawali($sheet, 'Tidak ada transaksi pada periode ini.');

        $this->assertArrayHasKey("A{$baris}:H{$baris}", $sheet->getMergeCells());
        $this->assertBergaris($sheet, "A{$baris}");
    }

    public function test_excel_status_nonaktif_merah_dan_keterangan_saldo(): void
    {
        $sheet = $this->lembarPerAnggota();
        $pita = $this->barisDiawali($sheet, "3. {$this->andi->no_anggota} — Andi");

        // Status di pita judul berwarna lewat rich text: "Nonaktif" merah, "Aktif" hijau.
        $rich = $sheet->getCell("A{$pita}")->getValue();
        $this->assertInstanceOf(RichText::class, $rich);
        $bagian = $rich->getRichTextElements();
        $this->assertSame('Nonaktif', end($bagian)->getText());
        $this->assertSame(LembarLaporan::MERAH, end($bagian)->getFont()->getColor()->getRGB());

        $budi = $sheet->getCell('A'.$this->barisDiawali($sheet, "1. {$this->budi->no_anggota}"))->getValue()->getRichTextElements();
        $this->assertSame('Aktif', end($budi)->getText());
        $this->assertSame(LembarLaporan::HIJAU, end($budi)->getFont()->getColor()->getRGB());

        $keterangan = $pita + 1;
        $this->assertSame(Anggota::KETERANGAN_SALDO_NONAKTIF, $sheet->getCell("A{$keterangan}")->getValue());
        $this->assertArrayHasKey("A{$keterangan}:H{$keterangan}", $sheet->getMergeCells());
        $this->assertTrue($sheet->getStyle("A{$keterangan}")->getFont()->getItalic());
    }

    // ---------------------------------------------------------------
    // Laporan keseluruhan (3 sheet)
    // ---------------------------------------------------------------

    public function test_excel_keseluruhan_tiga_sheet_bergaya_seperti_pdf(): void
    {
        $buku = $this->keseluruhan();

        $this->assertSame(['Ringkasan', 'Saldo Anggota', 'Transaksi'], $buku->getSheetNames());

        foreach (['Ringkasan' => 'D', 'Saldo Anggota' => 'I', 'Transaksi' => 'J'] as $nama => $kolomAkhir) {
            $sheet = $buku->getSheetByName($nama);

            $this->assertKepala($sheet, 'LAPORAN SIMPANAN KESELURUHAN', $kolomAkhir);
            $this->assertHeaderTabel($sheet, "A8:{$kolomAkhir}8");
            $this->assertBergaris($sheet, "{$kolomAkhir}9");
            $this->assertPengaturanCetak($sheet);
        }

        // Ringkasan: angka Rupiah, nilai 0 tetap angka, baris Total bold.
        $ringkasan = $buku->getSheetByName('Ringkasan');
        $this->assertRupiah($ringkasan, 'B9', 300000);
        $this->assertRupiah($ringkasan, 'C9', 0); // Pokok tidak pernah ditarik
        $total = $this->cariBaris($ringkasan, 'Total');
        $this->assertTrue($ringkasan->getStyle("A{$total}:D{$total}")->getFont()->getBold());
        $this->assertRupiah($ringkasan, "D{$total}", 475000);

        // Saldo Anggota: tanggal asli, status berwarna.
        $saldo = $buku->getSheetByName('Saldo Anggota');
        $this->assertTanggal($saldo, 'E9', '2026-11-01');
        $this->assertSame('Aktif', $saldo->getCell('D9')->getValue());
        $this->assertWarna($saldo, 'D9', LembarLaporan::HIJAU);
        $this->assertSame('Nonaktif', $saldo->getCell('D11')->getValue());
        $this->assertWarna($saldo, 'D11', LembarLaporan::MERAH);
        $this->assertRupiah($saldo, 'G10', 0); // Siti Wajib 0

        // Transaksi: tanggal asli, nama nonaktif bertanda, warna jenis transaksi.
        $transaksi = $buku->getSheetByName('Transaksi');
        $this->assertTanggal($transaksi, 'B9', '2026-11-01');
        $this->assertWarna($transaksi, 'F9', LembarLaporan::HIJAU);
        $this->assertRupiah($transaksi, 'G9', 100000);
        $nama = array_map(fn ($i) => $transaksi->getCell("D{$i}")->getValue(), range(9, 14));
        $this->assertContains('Andi (Nonaktif)', $nama);
        $tarik = array_search('Tarik', array_map(fn ($i) => $transaksi->getCell("F{$i}")->getValue(), range(9, 14)), true);
        $this->assertWarna($transaksi, 'F'.(9 + $tarik), LembarLaporan::MERAH);
        $this->assertTrue($transaksi->getStyle('J9')->getAlignment()->getWrapText());
    }

    public function test_excel_tetap_terunduh_dengan_nama_file_benar(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel'))->assertOk();
        $this->actingAs($this->admin)->get(route('laporan.keseluruhan.excel'))->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Semua-Anggota-20261112.xlsx');
        Excel::assertDownloaded('Laporan-Simpanan-Keseluruhan-20261112.xlsx');
    }
}
