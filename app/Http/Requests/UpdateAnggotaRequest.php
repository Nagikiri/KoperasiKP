<?php

namespace App\Http\Requests;

use App\Models\Anggota;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAnggotaRequest extends StoreAnggotaRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'nik' => ['required', 'digits:16', Rule::unique('anggota', 'nik')->ignore($this->route('anggota'))],
        ]);
    }

    /**
     * BR-03: tanggal bergabung tidak boleh setelah transaksi pertama anggota.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('tanggal_bergabung')) {
                return;
            }

            /** @var Anggota $anggota */
            $anggota = $this->route('anggota');

            $transaksiPertama = $anggota->transaksiSimpanan()->min('tanggal_transaksi');

            if ($transaksiPertama === null) {
                return;
            }

            $tanggalPertama = Carbon::parse($transaksiPertama)->startOfDay();
            $tanggalBergabung = Carbon::createFromFormat('Y-m-d', $this->input('tanggal_bergabung'))->startOfDay();

            if ($tanggalBergabung->gt($tanggalPertama)) {
                $validator->errors()->add(
                    'tanggal_bergabung',
                    'Tanggal bergabung tidak boleh setelah transaksi pertama anggota (' . Format::tanggal($tanggalPertama) . ').'
                );
            }
        });
    }
}
