<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSetorRequest;
use App\Http\Requests\StoreTarikRequest;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\SimpananService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransaksiSimpananController extends Controller
{
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
        $anggotaList = Anggota::query()->orderBy('nama')->get(['id', 'no_anggota', 'nama', 'status']);
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();

        $filter = $this->filterRiwayat($request, $anggotaList, $jenisSimpanan);

        // Satu kueri dasar untuk daftar dan ringkasan (H.3.6), dengan filter yang sama.
        $kueri = fn () => TransaksiSimpanan::query()
            ->when($filter['anggota_id'], fn ($query, $id) => $query->where('anggota_id', (int) $id))
            ->filter(array_intersect_key($filter, array_flip(['cari', 'jenis_transaksi', 'jenis_simpanan_id', 'rentang', 'dari', 'sampai'])));

        $riwayat = $kueri()
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $agregat = $kueri()
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->toBase()
            ->first();

        return view('transaksi.riwayat', [
            'riwayat' => $riwayat,
            'anggotaList' => $anggotaList,
            'jenisSimpanan' => $jenisSimpanan,
            'filter' => $filter,
            'ringkasan' => [
                'total_setor' => round((float) $agregat->total_setor, 2),
                'total_tarik' => round((float) $agregat->total_tarik, 2),
            ],
        ]);
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
