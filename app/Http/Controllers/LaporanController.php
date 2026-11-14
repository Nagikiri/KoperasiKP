<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKeseluruhanExport;
use App\Exports\LaporanSimpananExport;
use App\Http\Requests\LaporanAnggotaRequest;
use App\Http\Requests\LaporanKeseluruhanRequest;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Services\LaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function __construct(private LaporanService $laporanService) {}

    /**
     * Pratinjau laporan per anggota (FR-16, revisi R6). Tanpa `tampilkan=1`:
     * hanya form, tanpa validasi dan tanpa laporan. Dengan `tampilkan=1`:
     * filter divalidasi, lalu laporan ditampilkan (termasuk mode semua anggota).
     */
    public function anggota(Request $request): View
    {
        $filter = LaporanAnggotaRequest::filterDari($request);
        $laporan = null;

        if ($request->query('tampilkan') === '1') {
            $aturan = new LaporanAnggotaRequest;
            $request->validate($aturan->rules(), $aturan->messages());

            $laporan = $this->buatLaporan($request, $filter);
        }

        return view('laporan.anggota', [
            'anggotaList' => Anggota::query()->orderBy('nama')->get(['id', 'no_anggota', 'nama', 'status']),
            'filter' => $filter,
            'laporan' => $laporan,
        ]);
    }

    public function pdf(LaporanAnggotaRequest $request): Response
    {
        $filter = LaporanAnggotaRequest::filterDari($request);
        $laporan = $this->buatLaporan($request, $filter);

        return Pdf::loadView('laporan.pdf', $laporan)
            ->setPaper('a4', 'landscape')
            ->download($this->namaFile($filter, $laporan, 'pdf'));
    }

    public function excel(LaporanAnggotaRequest $request): BinaryFileResponse
    {
        $filter = LaporanAnggotaRequest::filterDari($request);
        $laporan = $this->buatLaporan($request, $filter);

        return Excel::download(new LaporanSimpananExport($laporan), $this->namaFile($filter, $laporan, 'xlsx'));
    }

    /**
     * Pratinjau laporan keseluruhan (FR-21). Transaksi dipaginasi 50 per halaman,
     * ringkasan dan saldo anggota tampil penuh.
     */
    public function keseluruhan(LaporanKeseluruhanRequest $request): View
    {
        $filter = $request->filter();

        return view('laporan.keseluruhan', [
            'filter' => $filter,
            'jenisSimpanan' => JenisSimpanan::query()->orderBy('id')->get(),
            'laporan' => $this->buatLaporanKeseluruhan($request, $filter, 50),
        ]);
    }

    public function pdfKeseluruhan(LaporanKeseluruhanRequest $request): Response
    {
        $laporan = $this->buatLaporanKeseluruhan($request, $request->filter());

        return Pdf::loadView('laporan.pdf-keseluruhan', $laporan)
            ->setPaper('a4', 'landscape')
            ->download($this->namaFileKeseluruhan($laporan, 'pdf'));
    }

    public function excelKeseluruhan(LaporanKeseluruhanRequest $request): BinaryFileResponse
    {
        $laporan = $this->buatLaporanKeseluruhan($request, $request->filter());

        return Excel::download(new LaporanKeseluruhanExport($laporan), $this->namaFileKeseluruhan($laporan, 'xlsx'));
    }

    /**
     * @param  array<string, string|null>  $filter
     * @return array<string, mixed>
     */
    private function buatLaporanKeseluruhan(Request $request, array $filter, ?int $perHalaman = null): array
    {
        $laporan = $this->laporanService->keseluruhan($filter, $perHalaman);
        $laporan['dicetak_oleh'] = $request->user()->nama;

        return $laporan;
    }

    /**
     * @param  array<string, mixed>  $laporan
     */
    private function namaFileKeseluruhan(array $laporan, string $ekstensi): string
    {
        return 'Laporan-Simpanan-Keseluruhan-'.$laporan['dicetak_pada']->format('Ymd').'.'.$ekstensi;
    }

    /**
     * @param  array<string, string|null>  $filter  filter yang sudah lolos validasi
     * @return array<string, mixed>
     */
    private function buatLaporan(Request $request, array $filter): array
    {
        $laporan = $this->laporanService->perAnggota($filter);
        $laporan['dicetak_oleh'] = $request->user()->nama;

        return $laporan;
    }

    /**
     * Satu anggota: `Laporan-Simpanan-{no_anggota}-{Ymd}`; semua anggota:
     * `Laporan-Simpanan-Semua-Anggota-{Ymd}` (revisi R9).
     *
     * @param  array<string, string|null>  $filter
     * @param  array<string, mixed>  $laporan
     */
    private function namaFile(array $filter, array $laporan, string $ekstensi): string
    {
        $subjek = $filter['anggota_id'] !== null && $laporan['anggota']->isNotEmpty()
            ? $laporan['anggota']->first()['no_anggota']
            : 'Semua-Anggota';

        return 'Laporan-Simpanan-'.$subjek.'-'.$laporan['dicetak_pada']->format('Ymd').'.'.$ekstensi;
    }
}
