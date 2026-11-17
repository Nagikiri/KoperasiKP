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
        // F2d H.2.1: tiga kunci filter dashboard ikut diterima (sejalan F4). Nilai tidak valid
        // untuk cari/jenis_transaksi/rentang menjadi null, sama seperti di dashboard.
        $jenisTransaksi = $this->stringAtauNull($request->query('jenis_transaksi'));
        $rentang = $this->stringAtauNull($request->query('rentang'));

        $filter = [
            'anggota_id' => $this->stringAtauNull($request->query('anggota_id')),
            'jenis_simpanan_id' => $this->stringAtauNull($request->query('jenis_simpanan_id')),
            'cari' => $this->stringAtauNull($request->query('cari')),
            'jenis_transaksi' => in_array($jenisTransaksi, [TransaksiSimpanan::SETOR, TransaksiSimpanan::TARIK], true) ? $jenisTransaksi : null,
            'rentang' => in_array($rentang, TransaksiSimpanan::RENTANG, true) ? $rentang : null,
        ];

        $riwayat = TransaksiSimpanan::query()
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->when($filter['anggota_id'], fn ($query, $id) => $query->where('anggota_id', $id))
            ->filter(array_intersect_key($filter, array_flip(['cari', 'jenis_transaksi', 'jenis_simpanan_id', 'rentang'])))
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('transaksi.riwayat', [
            'riwayat' => $riwayat,
            'anggotaList' => Anggota::query()->orderBy('nama')->get(['id', 'no_anggota', 'nama', 'status']),
            'jenisSimpanan' => JenisSimpanan::query()->orderBy('id')->get(),
            'filter' => $filter,
        ]);
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
