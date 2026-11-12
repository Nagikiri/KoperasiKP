<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnggotaRequest;
use App\Http\Requests\UpdateAnggotaRequest;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Services\NomorAnggotaGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnggotaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $search = $search === '' ? null : $search;

        $anggota = Anggota::query()
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', '%' . $search . '%')
                        ->orWhere('no_anggota', 'like', '%' . $search . '%');
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
        return view('anggota.create');
    }

    public function store(StoreAnggotaRequest $request, NomorAnggotaGenerator $generator): RedirectResponse
    {
        $anggota = DB::transaction(function () use ($request, $generator) {
            return Anggota::create(array_merge($request->validated(), [
                'no_anggota' => $generator->generate(),
                'status' => Anggota::STATUS_AKTIF,
            ]));
        });

        return redirect()
            ->route('anggota.show', $anggota)
            ->with('success', "Anggota {$anggota->nama} berhasil ditambahkan dengan nomor {$anggota->no_anggota}.");
    }

    public function show(Anggota $anggota): View
    {
        $saldoPerJenis = JenisSimpanan::query()
            ->orderBy('id')
            ->get()
            ->map(fn (JenisSimpanan $jenis) => [
                'nama_jenis' => $jenis->nama_jenis,
                'saldo' => $anggota->saldoPerJenis($jenis->id),
            ])
            ->values();

        $riwayatTransaksi = $anggota->transaksiSimpanan()
            ->with(['jenisSimpanan', 'staff'])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(10);

        return view('anggota.show', [
            'anggota' => $anggota,
            'saldoPerJenis' => $saldoPerJenis,
            'totalSaldo' => round((float) $saldoPerJenis->sum('saldo'), 2),
            'riwayatTransaksi' => $riwayatTransaksi,
        ]);
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
