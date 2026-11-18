<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnggotaRequest;
use App\Http\Requests\UpdateAnggotaRequest;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\NomorAnggotaGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnggotaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $search = $search === '' ? null : $search;

        // F3b B.7: total simpanan (Σsetor − Σtarik semua jenis) lewat subquery, satu query bersama paginate.
        $totalSimpanan = TransaksiSimpanan::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE -jumlah END), 0)")
            ->whereColumn('transaksi_simpanan.anggota_id', 'anggota.id');

        $anggota = Anggota::query()
            ->select('anggota.*')
            ->selectSub($totalSimpanan, 'total_simpanan')
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('no_anggota', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('anggota.index', [
            'anggota' => $anggota,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('anggota.create', [
            'kodeReferensi' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreAnggotaRequest $request, NomorAnggotaGenerator $generator): RedirectResponse
    {
        // BR-19: kiriman ganda (dicek sebelum validasi NIK unik di StoreAnggotaRequest).
        if (($duplikat = $request->anggotaDuplikat()) !== null) {
            return $this->redirectTersimpan($duplikat);
        }

        $data = $request->validated();

        try {
            $anggota = DB::transaction(function () use ($data, $generator) {
                return Anggota::create(array_merge($data, [
                    'no_anggota' => $generator->generate(),
                    'status' => Anggota::STATUS_AKTIF,
                ]));
            });
        } catch (UniqueConstraintViolationException $e) {
            // Pengaman terakhir: dua kiriman lolos cek bersamaan.
            if (($duplikat = Anggota::where('kode_referensi', $data['kode_referensi'])->first()) !== null) {
                return $this->redirectTersimpan($duplikat);
            }

            if (Anggota::where('nik', $data['nik'])->exists()) {
                throw ValidationException::withMessages(['nik' => StoreAnggotaRequest::PESAN_NIK_TERDAFTAR]);
            }

            throw $e;
        }

        return $this->redirectTersimpan($anggota);
    }

    private function redirectTersimpan(Anggota $anggota): RedirectResponse
    {
        return redirect()
            ->route('anggota.show', $anggota)
            ->with('success', "Anggota {$anggota->nama} berhasil ditambahkan dengan nomor {$anggota->no_anggota}.");
    }

    public function show(Request $request, Anggota $anggota): View
    {
        $jenisSimpanan = JenisSimpanan::query()->orderBy('id')->get();

        $saldoPerJenis = $jenisSimpanan
            ->map(fn (JenisSimpanan $jenis) => [
                'nama_jenis' => $jenis->nama_jenis,
                'saldo' => $anggota->saldoPerJenis($jenis->id),
            ])
            ->values();

        // F3b D.4–D.5: filter riwayat transaksi anggota ini lewat scopeFilter yang sama dengan dashboard.
        $filterRiwayat = $this->filterRiwayat($request, $jenisSimpanan);

        $riwayatTransaksi = $anggota->transaksiSimpanan()
            ->filter($filterRiwayat)
            ->with(['jenisSimpanan', 'staff'])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->fragment('riwayat');

        // Satu query agregasi dengan filter yang sama (D.4 ringkasan periode).
        $agregat = $anggota->transaksiSimpanan()
            ->filter($filterRiwayat)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->toBase()
            ->first();

        return view('anggota.show', [
            'anggota' => $anggota,
            'saldoPerJenis' => $saldoPerJenis,
            'totalSaldo' => round((float) $saldoPerJenis->sum('saldo'), 2),
            'riwayatTransaksi' => $riwayatTransaksi,
            'jenisSimpanan' => $jenisSimpanan,
            'filterRiwayat' => $filterRiwayat,
            'ringkasanRiwayat' => [
                'total_setor' => round((float) $agregat->total_setor, 2),
                'total_tarik' => round((float) $agregat->total_tarik, 2),
            ],
            // Membedakan "kosong karena filter" dari "belum punya transaksi sama sekali".
            'punyaTransaksi' => $riwayatTransaksi->total() > 0 || $anggota->transaksiSimpanan()->exists(),
        ]);
    }

    /**
     * Filter riwayat detail anggota (F3b D.5, H.2.1, H.5.3). Nilai tidak valid menjadi null.
     * rentang=kustom tanpa dari DAN sampai yang valid dianggap tanpa filter; dari/sampai hanya terisi saat kustom.
     *
     * @param  \Illuminate\Support\Collection<int, JenisSimpanan>  $jenisSimpanan
     * @return array{jenis_transaksi: ?string, jenis_simpanan_id: ?string, rentang: ?string, dari: ?string, sampai: ?string}
     */
    private function filterRiwayat(Request $request, $jenisSimpanan): array
    {
        $teks = fn (string $kunci): string => is_scalar($request->query($kunci)) ? trim((string) $request->query($kunci)) : '';

        $jenisTransaksi = $teks('jenis_transaksi');
        $jenisSimpananId = $teks('jenis_simpanan_id');
        $rentang = $teks('rentang');

        $kustom = $rentang === 'kustom' ? TransaksiSimpanan::rentangKustom($teks('dari'), $teks('sampai')) : null;

        if (! in_array($rentang, TransaksiSimpanan::RENTANG_RIWAYAT_ANGGOTA, true) || ($rentang === 'kustom' && $kustom === null)) {
            $rentang = null;
        }

        return [
            'jenis_transaksi' => in_array($jenisTransaksi, [TransaksiSimpanan::SETOR, TransaksiSimpanan::TARIK], true) ? $jenisTransaksi : null,
            'jenis_simpanan_id' => $jenisSimpanan->contains(fn (JenisSimpanan $jenis) => (string) $jenis->id === $jenisSimpananId) ? $jenisSimpananId : null,
            'rentang' => $rentang,
            'dari' => $rentang === 'kustom' ? $kustom['dari'] : null,
            'sampai' => $rentang === 'kustom' ? $kustom['sampai'] : null,
        ];
    }

    public function edit(Anggota $anggota): View
    {
        return view('anggota.edit', [
            'anggota' => $anggota,
        ]);
    }

    public function update(UpdateAnggotaRequest $request, Anggota $anggota): RedirectResponse
    {
        $anggota->update($request->validated());

        return redirect()
            ->route('anggota.show', $anggota)
            ->with('success', "Data anggota {$anggota->nama} berhasil diperbarui.");
    }

    public function nonaktifkan(Anggota $anggota): RedirectResponse
    {
        if (! $anggota->isAktif()) {
            return redirect()
                ->route('anggota.index')
                ->with('info', "Anggota {$anggota->nama} sudah berstatus nonaktif.");
        }

        $anggota->update(['status' => Anggota::STATUS_NONAKTIF]);

        return redirect()
            ->route('anggota.index')
            ->with('success', "Anggota {$anggota->nama} berhasil dinonaktifkan.");
    }
}
