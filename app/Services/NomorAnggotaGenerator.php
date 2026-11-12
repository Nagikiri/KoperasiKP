<?php

namespace App\Services;

use App\Models\Anggota;

class NomorAnggotaGenerator
{
    /**
     * Membuat nomor anggota berikutnya dengan format KSP-{TAHUN}-{URUT 4 digit} (BR-01).
     * Wajib dipanggil di dalam DB::transaction.
     */
    public function generate(): string
    {
        $prefix = 'KSP-' . now()->format('Y') . '-';

        $terakhir = Anggota::where('no_anggota', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('no_anggota')
            ->value('no_anggota');

        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }
}
