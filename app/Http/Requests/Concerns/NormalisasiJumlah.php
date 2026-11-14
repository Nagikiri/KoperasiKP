<?php

namespace App\Http\Requests\Concerns;

/**
 * Menerima input rupiah berformat "2.000.000", "Rp 150.000", atau "2.000.000,00".
 * "Rp", spasi, dan titik ribuan dibuang; akhiran ",00" dibuang. Sisa koma
 * desimal lain (mis. "1.500,50") dibiarkan supaya ditolak rule `integer`.
 */
trait NormalisasiJumlah
{
    protected function prepareForValidation(): void
    {
        $jumlah = $this->input('jumlah');

        if (! is_string($jumlah)) {
            return;
        }

        $jumlah = str_ireplace('rp', '', $jumlah);
        $jumlah = preg_replace('/\s+/u', '', $jumlah);
        $jumlah = str_replace('.', '', $jumlah);

        if (str_ends_with($jumlah, ',00')) {
            $jumlah = substr($jumlah, 0, -3);
        }

        $this->merge(['jumlah' => $jumlah]);
    }
}
