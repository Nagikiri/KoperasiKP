<?php

namespace App\Http\Controllers;

use App\Exports\RiwayatTransaksiExport;
use App\Http\Requests\StoreSetorRequest;
use App\Http\Requests\StoreTarikRequest;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\SimpananService;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransaksiSimpananController extends Controller
{
    /**
     * Batas baris ekspor PDF Riwayat; Excel tanpa batas. F5 H.5.7 menulis 2.000, diturunkan ke 500 atas keputusan
     * pengguna setelah diukur: dompdf ±200 MB/±7 detik untuk 500 baris, ±1,7 GB/±75 detik untuk 2.000 baris.
     */
    public const BATAS_PDF_RIWAYAT = 500;

    public const JUDUL_EKSPOR_RIWAYAT = 'Riwayat Transaksi Simpanan';

    /** Label rentang Riwayat (dropdown waktu dan label periode), kunci kosong = tanpa filter. */
    public const LABEL_RENTANG_RIWAYAT = [
        '' => 'Semua waktu',
        'hari_ini' => 'Hari ini',
        '7_hari' => '7 hari',
        '30_hari' => '30 hari',
        'bulan_ini' => 'Bulan ini',
        'bulan_lalu' => 'Bulan lalu',
        '3_bulan' => '3 bulan terakhir',
        'tahun_ini' => 'Tahun ini',
    ];

    public function __construct(private SimpananService $simpananService) {}

    public function createSetor(Request $request): View
    {
        $anggotaAktif = $this->anggotaAktif();

        return view('transaksi.setor', [
            'anggotaAktif' => $anggotaAktif,
            'jenisSimpanan' => JenisSimpanan::query()->orderBy('id')->get(),
            'tanggalHariIni' => now()->format('Y-m-d'),
            'anggotaTerpilih' => $this->anggotaTerpilih($request, $anggotaAktif),
            'kodeReferensi' => (string) Str::uuid(),
        ]);
    }

    public function storeSetor(StoreSetorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $transaksi = $this->simpananService->setor(
            (int) $data['anggota_id'],
            (int) $data['jenis_simpanan_id'],
            (int) $data['jumlah'],
            $data['tanggal_transaksi'],
            (int) $request->user()->id,
            $data['kode_referensi'],
            $data['keterangan'] ?? null,
        );

        if (! $transaksi->wasRecentlyCreated) {
            return $this->redirectDuplikat();
        }

        $transaksi->load(['anggota', 'jenisSimpanan']);

        return redirect()
            ->route('transaksi.riwayat')
            ->with('success', "Setoran {$transaksi->jenisSimpanan->nama_jenis} sebesar ".Format::rupiah($transaksi->jumlah)." untuk {$transaksi->anggota->nama} berhasil dicatat.");
    }

    public function createTarik(Request $request): View
    {
        $anggotaAktif = $this->anggotaAktif();
        $jenisSukarela = JenisSimpanan::sukarela();

        // Satu query agregasi untuk saldo Sukarela semua anggota.
        $saldoSukarelaPerAnggota = TransaksiSimpanan::query()
            ->where('jenis_simpanan_id', $jenisSukarela->id)
            ->selectRaw("anggota_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS saldo")
            ->groupBy('anggota_id')
            ->pluck('saldo', 'anggota_id')
            ->map(fn ($saldo) => round((float) $saldo, 2))
            ->all();

        return view('transaksi.tarik', [
            'anggotaAktif' => $anggotaAktif,
            'jenisSukarela' => $jenisSukarela,
            'saldoSukarelaPerAnggota' => $saldoSukarelaPerAnggota,
            'tanggalHariIni' => now()->format('Y-m-d'),
            'anggotaTerpilih' => $this->anggotaTerpilih($request, $anggotaAktif),
            'kodeReferensi' => (string) Str::uuid(),
        ]);
    }

    public function storeTarik(StoreTarikRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $transaksi = $this->simpananService->tarik(
            (int) $data['anggota_id'],
            (int) $data['jumlah'],
            $data['tanggal_transaksi'],
            (int) $request->user()->id,
            $data['kode_referensi'],
            $data['keterangan'] ?? null,
        );

        if (! $transaksi->wasRecentlyCreated) {
            return $this->redirectDuplikat();
        }

        $transaksi->load('anggota');

        return redirect()
            ->route('transaksi.riwayat')
            ->with('success', 'Penarikan Simpanan Sukarela sebesar '.Format::rupiah($transaksi->jumlah)." untuk {$transaksi->anggota->nama} berhasil dicatat.");
    }

    public function riwayat(Request $request): View
    {
        $anggotaList = $this->daftarAnggotaRiwayat();
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();

        $filter = $this->filterRiwayat($request, $anggotaList, $jenisSimpanan);

        // Satu kueri dasar untuk daftar dan ringkasan (H.3.6), dengan filter yang sama.
        $riwayat = $this->kueriRiwayat($filter)
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('transaksi.riwayat', [
            'riwayat' => $riwayat,
            'anggotaList' => $anggotaList,
            'jenisSimpanan' => $jenisSimpanan,
            'filter' => $filter,
            'ringkasan' => $this->ringkasanRiwayat($filter),
        ]);
    }

    /**
     * Ekspor PDF Riwayat (F5 H.5): filter dan kueri sama persis dengan halaman, urut lama → baru.
     * Lebih dari BATAS_PDF_RIWAYAT baris: tidak dirender, kembali ke Riwayat dengan pesan info.
     */
    public function pdfRiwayat(Request $request): Response|RedirectResponse
    {
        $anggotaList = $this->daftarAnggotaRiwayat();
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();
        $filter = $this->filterRiwayat($request, $anggotaList, $jenisSimpanan);

        if ($this->kueriRiwayat($filter)->count() > self::BATAS_PDF_RIWAYAT) {
            return redirect()
                ->route('transaksi.riwayat', array_filter($filter, fn ($nilai) => $nilai !== null))
                ->with('info', 'Data terlalu banyak untuk PDF. Persempit filter atau unduh Excel.');
        }

        $transaksi = $this->kueriRiwayat($filter)
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('transaksi.pdf-riwayat', [
            'kepala' => $this->kepalaEksporRiwayat($request, $filter, $anggotaList, $jenisSimpanan),
            'transaksi' => $transaksi,
            'ringkasan' => $this->ringkasanRiwayat($filter) + ['jumlah' => $transaksi->count()],
        ])
            ->setPaper('a4', 'landscape')
            ->download('riwayat-transaksi-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Ekspor Excel Riwayat (F5 H.5): FromQuery (dibaca per potong), tanpa batas baris, urut lama → baru.
     */
    public function excelRiwayat(Request $request): BinaryFileResponse
    {
        $anggotaList = $this->daftarAnggotaRiwayat();
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();
        $filter = $this->filterRiwayat($request, $anggotaList, $jenisSimpanan);

        $kueri = $this->kueriRiwayat($filter)
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->orderBy('tanggal_transaksi')
            ->orderBy('id');

        return Excel::download(
            new RiwayatTransaksiExport(
                $kueri,
                $this->kepalaEksporRiwayat($request, $filter, $anggotaList, $jenisSimpanan),
                $this->ringkasanRiwayat($filter) + ['jumlah' => $this->kueriRiwayat($filter)->count()],
            ),
            'riwayat-transaksi-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Label periode Riwayat (F5 H.5.5): satu helper untuk ringkasan halaman dan kepala ekspor.
     * Rentang kustom memakai Format::tanggalPendek; rentang berjalan memakai label dropdown.
     *
     * @param  array<string, string|null>  $filter
     */
    public static function labelPeriodeRiwayat(array $filter): string
    {
        $rentang = $filter['rentang'] ?? null;
        $dari = $filter['dari'] ?? null;
        $sampai = $filter['sampai'] ?? null;

        if ($rentang === 'kustom') {
            return match (true) {
                $dari !== null && $sampai !== null => Format::tanggalPendek($dari).' – '.Format::tanggalPendek($sampai),
                $dari !== null => 'Sejak '.Format::tanggalPendek($dari),
                $sampai !== null => 'Sampai '.Format::tanggalPendek($sampai),
                default => self::LABEL_RENTANG_RIWAYAT[''],
            };
        }

        return self::LABEL_RENTANG_RIWAYAT[$rentang ?? ''] ?? self::LABEL_RENTANG_RIWAYAT[''];
    }

    /**
     * Kueri dasar Riwayat (halaman dan ekspor): anggota_id + scopeFilter untuk kunci lainnya.
     *
     * @param  array<string, string|null>  $filter
     */
    private function kueriRiwayat(array $filter): Builder
    {
        return TransaksiSimpanan::query()
            ->when($filter['anggota_id'], fn ($query, $id) => $query->where('anggota_id', (int) $id))
            ->filter(array_intersect_key($filter, array_flip(['cari', 'jenis_transaksi', 'jenis_simpanan_id', 'rentang', 'dari', 'sampai'])));
    }

    /**
     * Total setor/tarik sesuai filter, satu query agregasi (H.3.6).
     *
     * @param  array<string, string|null>  $filter
     * @return array{total_setor: float, total_tarik: float}
     */
    private function ringkasanRiwayat(array $filter): array
    {
        $agregat = $this->kueriRiwayat($filter)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->toBase()
            ->first();

        return [
            'total_setor' => round((float) $agregat->total_setor, 2),
            'total_tarik' => round((float) $agregat->total_tarik, 2),
        ];
    }

    /**
     * Kepala dokumen ekspor Riwayat (F5 H.5.5): periode, ringkasan filter aktif, dan waktu unduh.
     *
     * @param  array<string, string|null>  $filter
     * @param  Collection<int, Anggota>  $anggotaList
     * @param  Collection<int, JenisSimpanan>  $jenisSimpanan
     * @return array{judul: string, periode: string, filter: string, diunduh: string}
     */
    private function kepalaEksporRiwayat(Request $request, array $filter, Collection $anggotaList, Collection $jenisSimpanan): array
    {
        $anggota = $anggotaList->first(fn (Anggota $anggota) => (string) $anggota->id === $filter['anggota_id']);
        $jenis = $jenisSimpanan->first(fn (JenisSimpanan $jenis) => (string) $jenis->id === $filter['jenis_simpanan_id']);

        $bagian = [
            'Anggota: '.($anggota ? $anggota->no_anggota.' - '.Format::namaAnggota($anggota->nama, $anggota->status) : 'Semua'),
            'Transaksi: '.($filter['jenis_transaksi'] !== null ? ucfirst($filter['jenis_transaksi']) : 'Semua'),
            'Jenis: '.($jenis?->nama_jenis ?? 'Semua'),
        ];
        if ($filter['cari'] !== null) {
            $bagian[] = 'Cari: "'.$filter['cari'].'"';
        }

        return [
            'judul' => self::JUDUL_EKSPOR_RIWAYAT,
            'periode' => self::labelPeriodeRiwayat($filter),
            'filter' => implode('; ', $bagian),
            'diunduh' => 'Diunduh pada '.Format::tanggalWaktu(now()).' oleh '.$request->user()->nama,
        ];
    }

    /**
     * @return Collection<int, Anggota>
     */
    private function daftarAnggotaRiwayat(): Collection
    {
        return Anggota::query()->orderBy('nama')->get(['id', 'no_anggota', 'nama', 'status']);
    }

    /**
     * Filter Riwayat (F2d H.2.1, F4 H.3.1–H.3.3): tujuh kunci berurutan, nilai string|null; tidak valid → null.
     * anggota_id/jenis_simpanan_id harus cocok dengan daftar yang sudah dimuat (tanpa query tambahan).
     * rentang=kustom tanpa dari DAN sampai yang valid = tanpa filter; dari/sampai hanya terisi saat kustom.
     *
     * @param  Collection<int, Anggota>  $anggotaList
     * @param  Collection<int, JenisSimpanan>  $jenisSimpanan
     * @return array{anggota_id: ?string, jenis_simpanan_id: ?string, cari: ?string, jenis_transaksi: ?string, rentang: ?string, dari: ?string, sampai: ?string}
     */
    private function filterRiwayat(Request $request, Collection $anggotaList, Collection $jenisSimpanan): array
    {
        $anggotaId = $this->stringAtauNull($request->query('anggota_id'));
        $jenisSimpananId = $this->stringAtauNull($request->query('jenis_simpanan_id'));
        $jenisTransaksi = $this->stringAtauNull($request->query('jenis_transaksi'));
        $rentang = $this->stringAtauNull($request->query('rentang'));

        $kustom = $rentang === 'kustom'
            ? TransaksiSimpanan::rentangKustom($this->stringAtauNull($request->query('dari')), $this->stringAtauNull($request->query('sampai')))
            : null;

        if (! in_array($rentang, TransaksiSimpanan::RENTANG_RIWAYAT, true) || ($rentang === 'kustom' && $kustom === null)) {
            $rentang = null;
        }

        return [
            'anggota_id' => $anggotaList->contains(fn (Anggota $anggota) => (string) $anggota->id === $anggotaId) ? $anggotaId : null,
            'jenis_simpanan_id' => $jenisSimpanan->contains(fn (JenisSimpanan $jenis) => (string) $jenis->id === $jenisSimpananId) ? $jenisSimpananId : null,
            'cari' => $this->stringAtauNull($request->query('cari')),
            'jenis_transaksi' => in_array($jenisTransaksi, [TransaksiSimpanan::SETOR, TransaksiSimpanan::TARIK], true) ? $jenisTransaksi : null,
            'rentang' => $rentang,
            'dari' => $rentang === 'kustom' ? $kustom['dari'] : null,
            'sampai' => $rentang === 'kustom' ? $kustom['sampai'] : null,
        ];
    }

    /**
     * BR-19: kiriman ganda dengan kode_referensi yang sama.
     */
    private function redirectDuplikat(): RedirectResponse
    {
        return redirect()
            ->route('transaksi.riwayat')
            ->with('info', 'Transaksi ini sudah tercatat sebelumnya.');
    }

    /**
     * @return Collection<int, Anggota>
     */
    private function anggotaAktif(): Collection
    {
        return Anggota::query()->aktif()->orderBy('nama')->get(['id', 'no_anggota', 'nama']);
    }

    /**
     * Id anggota pra-pilih dari `?anggota_id=`, hanya jika milik anggota aktif.
     */
    private function anggotaTerpilih(Request $request, Collection $anggotaAktif): ?int
    {
        $id = $request->query('anggota_id');

        if (! is_scalar($id) || ! ctype_digit((string) $id)) {
            return null;
        }

        return $anggotaAktif->contains('id', (int) $id) ? (int) $id : null;
    }

    private function stringAtauNull(mixed $nilai): ?string
    {
        if (! is_scalar($nilai)) {
            return null;
        }

        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
