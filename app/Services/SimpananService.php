<?php

namespace App\Services;

use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use Illuminate\Support\Collection;

class SimpananService
{
    /**
     * Rekap simpanan per jenis (BR-14): total setor, total tarik, dan saldo.
     * Ketiga jenis selalu muncul walau belum ada transaksi. Mencakup anggota
     * aktif maupun nonaktif.
     *
     * @return Collection<int, array{jenis_simpanan_id: int, nama_jenis: string, total_setor: float, total_tarik: float, saldo: float}>
     */
    public function rekapPerJenis(): Collection
    {
        $agregat = TransaksiSimpanan::query()
            ->selectRaw("jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->groupBy('jenis_simpanan_id')
            ->get()
            ->keyBy('jenis_simpanan_id');

        return JenisSimpanan::query()
            ->orderBy('id')
            ->get()
            ->map(function (JenisSimpanan $jenis) use ($agregat) {
                $baris = $agregat->get($jenis->id);

                $totalSetor = round((float) ($baris->total_setor ?? 0), 2);
                $totalTarik = round((float) ($baris->total_tarik ?? 0), 2);

                return [
                    'jenis_simpanan_id' => $jenis->id,
                    'nama_jenis' => $jenis->nama_jenis,
                    'total_setor' => $totalSetor,
                    'total_tarik' => $totalTarik,
                    'saldo' => round($totalSetor - $totalTarik, 2),
                ];
            })
            ->values();
    }
}
