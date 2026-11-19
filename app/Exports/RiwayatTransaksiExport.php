<?php

namespace App\Exports;

use App\Exports\Sheets\LembarLaporan;
use App\Models\TransaksiSimpanan;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel Riwayat transaksi (F5 H.5.8). Baris dibaca lewat FromQuery (per potong, hemat memori),
 * dengan filter dan urutan yang sama seperti PDF Riwayat (lama → baru). Tanpa batas baris.
 *
 * Tata letak: baris 1 kop (kosong), 2 nama koperasi, 3 judul, 4 periode, 5 filter, 6 waktu unduh,
 * 7 jarak, 8 header tabel (diulang saat dicetak), lalu data dan ringkasan di akhir.
 * Gaya mengikuti Excel laporan (LembarLaporan): header abu-abu bold, garis tipis, Rupiah sebagai angka,
 * tanggal Excel asli, jenis transaksi hijau/merah. WithStrictNullComparison: nilai 0 tetap 0.
 */
class RiwayatTransaksiExport implements FromQuery, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    public const KOLOM = ['Tanggal', 'No. Anggota', 'Nama Anggota', 'Jenis Transaksi', 'Jenis Simpanan', 'Jumlah', 'Saldo Setelah Transaksi', 'Dicatat Oleh', 'Catatan'];

    public const BARIS_HEADER = 8;

    public const KOSONG = 'Tidak ada transaksi sesuai filter.';

    private const KOLOM_AKHIR = 'I';

    /**
     * @param  Builder<TransaksiSimpanan>  $kueri  kueri Riwayat yang sudah difilter dan diurutkan
     * @param  array{judul: string, periode: string, filter: string, diunduh: string}  $kepala
     * @param  array{total_setor: float, total_tarik: float, jumlah: int}  $ringkasan
     */
    public function __construct(
        private Builder $kueri,
        public readonly array $kepala,
        public readonly array $ringkasan,
    ) {}

    public function query(): Builder
    {
        return $this->kueri;
    }

    public function title(): string
    {
        return 'Riwayat Transaksi';
    }

    public function startCell(): string
    {
        return 'A'.self::BARIS_HEADER;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return self::KOLOM;
    }

    /**
     * @param  TransaksiSimpanan  $transaksi
     * @return array<int, mixed>
     */
    public function map($transaksi): array
    {
        return [
            ExcelDate::PHPToExcel($transaksi->tanggal_transaksi),
            $transaksi->anggota->no_anggota,
            Format::namaAnggota($transaksi->anggota->nama, $transaksi->anggota->status),
            ucfirst($transaksi->jenis_transaksi),
            $transaksi->jenisSimpanan->nama_jenis,
            (float) $transaksi->jumlah,
            (float) $transaksi->saldo_akhir,
            $transaksi->staff->nama,
            $transaksi->keterangan ?? '-',
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => fn (BeforeSheet $event) => $this->tulisKepala($event->sheet->getDelegate()),
            AfterSheet::class => fn (AfterSheet $event) => $this->terapkanGaya($event->sheet->getDelegate()),
        ];
    }

    private function tulisKepala(Worksheet $sheet): void
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $baris = [
            1 => [null, []],
            2 => [LembarLaporan::KOPERASI, ['tebal' => true, 'ukuran' => 13]],
            3 => [$this->kepala['judul'], ['tebal' => true, 'ukuran' => 16]],
            4 => ['Periode : '.$this->kepala['periode'], []],
            5 => ['Filter : '.$this->kepala['filter'], []],
            6 => [$this->kepala['diunduh'], ['ukuran' => 8, 'warna' => '555555']],
        ];

        foreach ($baris as $nomor => [$teks, $gaya]) {
            $rentang = "A{$nomor}:".self::KOLOM_AKHIR.$nomor;
            $sheet->setCellValue("A{$nomor}", $teks);
            $sheet->mergeCells($rentang);
            $style = $sheet->getStyle($rentang);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $style->getFont()->setBold($gaya['tebal'] ?? false);
            if (isset($gaya['ukuran'])) {
                $style->getFont()->setSize($gaya['ukuran']);
            }
            if (isset($gaya['warna'])) {
                $style->getFont()->getColor()->setRGB($gaya['warna']);
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(30); // area kop/logo, sama dengan Excel laporan
        $sheet->getStyle('A2:'.self::KOLOM_AKHIR.'2')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
    }

    private function terapkanGaya(Worksheet $sheet): void
    {
        $header = self::BARIS_HEADER;
        $awal = $header + 1;
        $akhir = $header + $this->ringkasan['jumlah'];
        $kolom = self::KOLOM_AKHIR;

        // Header tabel.
        $gayaHeader = $sheet->getStyle("A{$header}:{$kolom}{$header}");
        $gayaHeader->getFont()->setBold(true);
        $gayaHeader->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(LembarLaporan::ABU);
        $gayaHeader->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        if ($this->ringkasan['jumlah'] === 0) {
            $sheet->setCellValue("A{$awal}", self::KOSONG);
            $sheet->mergeCells("A{$awal}:{$kolom}{$awal}");
            $sheet->getStyle("A{$awal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$awal}")->getFont()->setItalic(true);
            $akhir = $awal;
        } else {
            $sheet->getStyle("A{$awal}:A{$akhir}")->getNumberFormat()->setFormatCode(LembarLaporan::FORMAT_TANGGAL);
            $sheet->getStyle("A{$awal}:A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$awal}:G{$akhir}")->getNumberFormat()->setFormatCode(LembarLaporan::FORMAT_RUPIAH);
            $sheet->getStyle("F{$awal}:G{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$awal}:I{$akhir}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$awal}:{$kolom}{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            // Warna jenis transaksi sama dengan PDF/Excel laporan (hijau setor, merah tarik), lewat conditional format.
            $sheet->getStyle("D{$awal}:D{$akhir}")->setConditionalStyles([
                $this->warnaJika('Setor', LembarLaporan::HIJAU),
                $this->warnaJika('Tarik', LembarLaporan::MERAH),
            ]);
        }

        $sheet->getStyle("A{$header}:{$kolom}{$akhir}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        // Ringkasan di akhir: label (merge A:E) dan nilai angka di F.
        $baris = $akhir + 2;
        $bersih = round($this->ringkasan['total_setor'] - $this->ringkasan['total_tarik'], 2);
        foreach ([
            ['Jumlah transaksi', $this->ringkasan['jumlah'], '#,##0'],
            ['Total setor', $this->ringkasan['total_setor'], LembarLaporan::FORMAT_RUPIAH],
            ['Total tarik', $this->ringkasan['total_tarik'], LembarLaporan::FORMAT_RUPIAH],
            ['Bersih', $bersih, '"+Rp" #,##0;"-Rp" #,##0;"Rp" 0'],
        ] as [$label, $nilai, $format]) {
            $sheet->setCellValue("A{$baris}", $label);
            $sheet->mergeCells("A{$baris}:E{$baris}");
            $sheet->setCellValue("F{$baris}", $nilai);
            $sheet->getStyle("F{$baris}")->getNumberFormat()->setFormatCode($format);
            $sheet->getStyle("A{$baris}:F{$baris}")->getFont()->setBold(true);
            $sheet->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$baris}:F{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A{$baris}:F{$baris}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(LembarLaporan::ABU);
            $baris++;
        }

        foreach (['A' => 18, 'B' => 16, 'C' => 28, 'D' => 14, 'E' => 20, 'F' => 16, 'G' => 18, 'H' => 18, 'I' => 40] as $huruf => $lebar) {
            $sheet->getColumnDimension($huruf)->setWidth($lebar);
        }

        $halaman = $sheet->getPageSetup();
        $halaman->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $halaman->setPaperSize(PageSetup::PAPERSIZE_A4);
        $halaman->setFitToWidth(1);
        $halaman->setFitToHeight(0);
        $halaman->setHorizontalCentered(true);
        $halaman->setRowsToRepeatAtTopByStartAndEnd($header, $header);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.3)->setFooter(0.3);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.str_replace('&', '&&', $this->kepala['diunduh']).'&R&8Halaman &P dari &N');
        $sheet->setShowGridlines(false);
        $sheet->setSelectedCells('A1');
    }

    private function warnaJika(string $teks, string $warna): Conditional
    {
        $kondisi = new Conditional;
        $kondisi->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_EQUAL)
            ->addCondition('"'.$teks.'"');
        $kondisi->getStyle()->getFont()->setBold(true)->getColor()->setRGB($warna);

        return $kondisi;
    }
}
