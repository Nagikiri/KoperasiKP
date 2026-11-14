<?php

namespace App\Exports\Sheets;

use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu sheet laporan Excel yang meniru PDF (revisi R3). Baris disusun lewat
 * method fluent (kepala, header, data, penuh, ...) dari array LaporanService,
 * tanpa query. Gaya diterapkan di AfterSheet:
 * kepala di-merge dan rata tengah, header bold berlatar abu-abu, semua sel
 * tabel bergaris tipis, angka berformat Rupiah, tanggal berupa tanggal Excel,
 * warna status/jenis transaksi, lebar kolom, dan pengaturan cetak
 * (A4 landscape, fit 1 halaman lebar, print titles, footer).
 *
 * WithStrictNullComparison: tanpa ini, maatwebsite menganggap 0 == null
 * sehingga nilai 0 menjadi sel kosong.
 */
class LembarLaporan implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    public const FORMAT_RUPIAH = '"Rp" #,##0';

    public const FORMAT_TANGGAL = '[$-421]d mmmm yyyy';

    public const ABU = 'D9D9D9';

    public const ABU_MUDA = 'F7F7F7';

    public const ABU_PITA = 'EDEDED';

    public const ABU_GELAP = 'A6A6A6';

    public const HIJAU = '15803D';

    public const MERAH = 'B91C1C';

    public const KOPERASI = 'KSP Karya Duta Utama';

    public const KETERANGAN = 'Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama';

    /** @var array<int, array<int, mixed>> */
    private array $baris = [];

    /**
     * Metadata gaya per nomor baris (1-based).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $meta = [];

    private ?int $barisJudulCetak = null;

    /** @var array{0: int, 1: int}|null rentang baris yang diulang saat dicetak */
    private ?array $barisCetakUlang = null;

    /** @var array<int, int> lebar minimum per kolom (1-based) */
    private array $lebarMinimum = [];

    private string $kakiCetak = '';

    public function __construct(private string $judul, private int $jumlahKolom) {}

    public function title(): string
    {
        return $this->judul;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->baris;
    }

    public function jumlahKolom(): int
    {
        return $this->jumlahKolom;
    }

    /**
     * Metadata baris (untuk test dan AfterSheet).
     *
     * @return array<int, array<string, mixed>>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    // ------------------------------------------------------------------
    // Penyusun baris
    // ------------------------------------------------------------------

    /**
     * Kepala laporan: baris kop/logo (kosong), nama koperasi, judul, Periode,
     * Filter, keterangan, lalu satu baris jarak. Semua di-merge selebar tabel.
     */
    public function kepala(string $judulLaporan, string $periode, string $filter): static
    {
        $this->tambah([null], ['jenis' => 'kepala', 'kop' => true]);
        $this->tambah([self::KOPERASI], ['jenis' => 'kepala', 'tebal' => true, 'ukuran' => 13, 'garisBawah' => true]);
        $this->tambah([$judulLaporan], ['jenis' => 'kepala', 'tebal' => true, 'ukuran' => 16]);
        $this->tambah(['Periode : '.$periode], ['jenis' => 'kepala']);
        $this->tambah(['Filter : '.$filter], ['jenis' => 'kepala']);
        $this->tambah([self::KETERANGAN], ['jenis' => 'kepala', 'ukuran' => 8, 'miring' => true, 'warnaTeks' => '555555']);

        return $this->kosong();
    }

    /**
     * Baris header tabel (bold, abu-abu, rata tengah, bergaris).
     *
     * @param  array<int, string|null>  $kolom
     * @param  array<int, array{0: int, 1: int}>  $merge  rentang kolom (1-based) yang di-merge
     */
    public function header(array $kolom, bool $judulCetak = false, array $merge = []): static
    {
        $this->tambah($kolom, ['jenis' => 'header', 'merge' => $merge]);

        if ($judulCetak) {
            $this->barisJudulCetak = count($this->baris);
        }

        return $this;
    }

    /**
     * Baris data tabel (bergaris). Opsi:
     * - uang: kolom berformat Rupiah; tanggal: kolom tanggal (nilai CarbonInterface);
     * - warna: [kolom => 'hijau'|'merah']; tengah, wrap: daftar kolom;
     * - tebal: bool; latar: warna hex; merge: rentang kolom.
     *
     * @param  array<int, mixed>  $sel
     * @param  array<string, mixed>  $opsi
     */
    public function data(array $sel, array $opsi = []): static
    {
        foreach ($opsi['tanggal'] ?? [] as $kolom) {
            $nilai = $sel[$kolom - 1] ?? null;
            $sel[$kolom - 1] = $nilai instanceof CarbonInterface ? ExcelDate::PHPToExcel($nilai) : $nilai;
        }

        return $this->tambah($sel, ['jenis' => 'data'] + $opsi);
    }

    /**
     * Satu teks di-merge selebar tabel (bergaris). Opsi: tebal, miring,
     * latar, warnaTeks, ukuran, tengah.
     *
     * @param  array<string, mixed>  $opsi
     */
    public function penuh(string $teks, array $opsi = []): static
    {
        return $this->tambah([$teks], ['jenis' => 'penuh'] + $opsi);
    }

    /**
     * Teks di-merge selebar tabel tanpa garis (misalnya "Dicetak pada ...").
     */
    public function catatan(string $teks): static
    {
        return $this->tambah([$teks], ['jenis' => 'catatan', 'ukuran' => 8, 'warnaTeks' => '555555']);
    }

    public function kosong(): static
    {
        return $this->tambah([null], ['jenis' => 'kosong']);
    }

    /**
     * Baris yang diulang di setiap halaman cetak (print titles), misalnya kepala
     * laporan bila tabel tidak punya satu header bersama.
     */
    public function ulangiBarisCetak(int $dari, int $sampai): static
    {
        $this->barisCetakUlang = [$dari, $sampai];

        return $this;
    }

    public function kakiCetak(string $teks): static
    {
        $this->kakiCetak = $teks;

        return $this;
    }

    /**
     * @param  array<int, int>  $lebar  [kolom (1-based) => lebar minimum]
     */
    public function lebarMinimum(array $lebar): static
    {
        $this->lebarMinimum = $lebar + $this->lebarMinimum;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $sel
     * @param  array<string, mixed>  $meta
     */
    private function tambah(array $sel, array $meta): static
    {
        $this->baris[] = array_values($sel);
        $this->meta[count($this->baris)] = $meta;

        return $this;
    }

    // ------------------------------------------------------------------
    // Gaya (AfterSheet)
    // ------------------------------------------------------------------

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->terapkanGaya($event->sheet->getDelegate()),
        ];
    }

    public function terapkanGaya(Worksheet $sheet): void
    {
        $kolomAkhir = Coordinate::stringFromColumnIndex($this->jumlahKolom);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach ($this->meta as $baris => $meta) {
            $rentang = "A{$baris}:{$kolomAkhir}{$baris}";
            $gaya = $sheet->getStyle($rentang);
            $gaya->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            switch ($meta['jenis']) {
                case 'kepala':
                    $sheet->mergeCells($rentang);
                    $gaya->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                    if (! empty($meta['kop'])) {
                        $sheet->getRowDimension($baris)->setRowHeight(30); // area kop/logo (tahap frontend)
                    }
                    if (! empty($meta['garisBawah'])) {
                        $gaya->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
                    }
                    break;

                case 'header':
                    $this->garis($sheet, $rentang);
                    $gaya->getFont()->setBold(true);
                    $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ABU);
                    $gaya->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                    break;

                case 'data':
                    $this->garis($sheet, $rentang);
                    $this->gayaData($sheet, $baris, $meta);
                    break;

                case 'penuh':
                    $sheet->mergeCells($rentang);
                    $this->garis($sheet, $rentang);
                    $gaya->getAlignment()->setHorizontal(($meta['tengah'] ?? true) ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT)->setWrapText(true);
                    if (! empty($meta['statusAkhir'])) {
                        $this->teksStatusBerwarna($sheet, "A{$baris}", $meta['statusAkhir'], ! empty($meta['tebal']));
                    }
                    break;

                case 'catatan':
                    $sheet->mergeCells($rentang);
                    break;
            }

            foreach ($meta['merge'] ?? [] as [$dari, $sampai]) {
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($dari).$baris.':'.Coordinate::stringFromColumnIndex($sampai).$baris);
            }

            // getStyle() memakai seleksi sel aktif bersama; ambil ulang untuk rentang baris ini.
            $gaya = $sheet->getStyle($rentang);
            $font = $gaya->getFont();
            if (! empty($meta['tebal'])) {
                $font->setBold(true);
            }
            if (! empty($meta['miring'])) {
                $font->setItalic(true);
            }
            if (! empty($meta['ukuran'])) {
                $font->setSize($meta['ukuran']);
            }
            if (! empty($meta['warnaTeks'])) {
                $font->getColor()->setRGB($meta['warnaTeks']);
            }
            if (! empty($meta['latar'])) {
                $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($meta['latar']);
            }
        }

        $this->aturLebarKolom($sheet);
        $this->aturCetak($sheet, $kolomAkhir);
    }

    /**
     * Akhir teks sel (status) diberi warna lewat rich text, misalnya
     * "... | Status: Nonaktif" dengan "Nonaktif" merah. Nilai sel tetap teks yang sama.
     *
     * @param  array{teks: string, warna: string}  $status
     */
    private function teksStatusBerwarna(Worksheet $sheet, string $sel, array $status, bool $tebal): void
    {
        $nilai = (string) $sheet->getCell($sel)->getValue();
        if (! str_ends_with($nilai, $status['teks'])) {
            return;
        }

        $rich = new RichText;
        $awal = $rich->createTextRun(substr($nilai, 0, -strlen($status['teks'])));
        $awal->getFont()->setBold($tebal)->setSize(11);
        $akhir = $rich->createTextRun($status['teks']);
        $akhir->getFont()->setBold(true)->setSize(11)->getColor()->setRGB($status['warna'] === 'hijau' ? self::HIJAU : self::MERAH);

        $sheet->getCell($sel)->setValue($rich);
    }

    private function garis(Worksheet $sheet, string $rentang): void
    {
        $sheet->getStyle($rentang)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function gayaData(Worksheet $sheet, int $baris, array $meta): void
    {
        $sel = fn (int $kolom) => $sheet->getStyle(Coordinate::stringFromColumnIndex($kolom).$baris);

        foreach ($meta['uang'] ?? [] as $kolom) {
            $sel($kolom)->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sel($kolom)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        foreach ($meta['format'] ?? [] as $kolom => $kode) {
            $sel($kolom)->getNumberFormat()->setFormatCode($kode);
            $sel($kolom)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        foreach ($meta['tanggal'] ?? [] as $kolom) {
            $sel($kolom)->getNumberFormat()->setFormatCode(self::FORMAT_TANGGAL);
            $sel($kolom)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        foreach ($meta['tengah'] ?? [] as $kolom) {
            $sel($kolom)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach ($meta['wrap'] ?? [] as $kolom) {
            $sel($kolom)->getAlignment()->setWrapText(true);
        }
        foreach ($meta['warna'] ?? [] as $kolom => $warna) {
            $sel($kolom)->getFont()->setBold(true)->getColor()->setRGB($warna === 'hijau' ? self::HIJAU : self::MERAH);
        }
    }

    /**
     * Lebar dari panjang isi sel tabel (header & data, tanpa sel merge),
     * dibatasi 6..40 karakter; kolom berformat uang/tanggal diberi ruang tampilan.
     */
    private function aturLebarKolom(Worksheet $sheet): void
    {
        $lebar = array_fill(1, $this->jumlahKolom, 6);

        foreach ($this->meta as $baris => $meta) {
            if (! in_array($meta['jenis'], ['header', 'data'], true)) {
                continue;
            }

            $dimerge = [];
            foreach ($meta['merge'] ?? [] as [$dari, $sampai]) {
                $dimerge = array_merge($dimerge, range($dari, $sampai));
            }

            foreach ($this->baris[$baris - 1] as $i => $nilai) {
                $kolom = $i + 1;
                if ($nilai === null || in_array($kolom, $dimerge, true)) {
                    continue;
                }

                if (in_array($kolom, $meta['uang'] ?? [], true)) {
                    $panjang = strlen('Rp '.number_format((float) $nilai, 0, ',', '.')) + 2;
                } elseif (in_array($kolom, $meta['tanggal'] ?? [], true)) {
                    $panjang = 18;
                } else {
                    $panjang = mb_strlen((string) $nilai) + 2;
                }

                $lebar[$kolom] = max($lebar[$kolom], $panjang);
            }
        }

        foreach ($lebar as $kolom => $nilai) {
            $nilai = max($nilai, $this->lebarMinimum[$kolom] ?? 0);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($kolom))->setWidth(min($nilai, 40));
        }
    }

    private function aturCetak(Worksheet $sheet, string $kolomAkhir): void
    {
        $halaman = $sheet->getPageSetup();
        $halaman->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $halaman->setPaperSize(PageSetup::PAPERSIZE_A4);
        $halaman->setFitToWidth(1);
        $halaman->setFitToHeight(0);
        $halaman->setHorizontalCentered(true);
        $halaman->setPrintArea('A1:'.$kolomAkhir.count($this->baris));

        if ($this->barisCetakUlang !== null) {
            $halaman->setRowsToRepeatAtTopByStartAndEnd(...$this->barisCetakUlang);
        } elseif ($this->barisJudulCetak !== null) {
            $halaman->setRowsToRepeatAtTopByStartAndEnd($this->barisJudulCetak, $this->barisJudulCetak);
        }

        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.3)->setFooter(0.3);

        if ($this->kakiCetak !== '') {
            $teks = str_replace('&', '&&', $this->kakiCetak);
            $sheet->getHeaderFooter()->setOddFooter('&L&8'.$teks.'&R&8Halaman &P dari &N');
        }

        $sheet->setShowGridlines(false);
        $sheet->setSelectedCells('A1');
    }
}
