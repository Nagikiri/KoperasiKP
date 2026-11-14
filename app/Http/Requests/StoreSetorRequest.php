<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiJumlah;
use App\Models\Anggota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSetorRequest extends FormRequest
{
    use NormalisasiJumlah;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kode_referensi' => ['required', 'uuid'],
            'anggota_id' => [
                'required',
                'integer',
                Rule::exists('anggota', 'id')->where('status', Anggota::STATUS_AKTIF),
            ],
            'jenis_simpanan_id' => ['required', 'integer', 'exists:jenis_simpanan,id'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'tanggal_transaksi' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anggota_id.exists' => 'Anggota tidak ditemukan atau sudah nonaktif.',
        ];
    }
}
