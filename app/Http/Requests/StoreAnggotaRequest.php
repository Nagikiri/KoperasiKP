<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnggotaRequest extends FormRequest
{
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
            'nama' => ['required', 'string', 'max:100'],
            'nik' => ['required', 'digits:16', 'unique:anggota,nik'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'no_telepon' => ['nullable', 'string', 'max:20', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'tanggal_bergabung' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik.digits' => 'NIK harus terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar atas anggota lain.',
            'no_telepon.regex' => 'Nomor telepon tidak valid. Contoh: 081234567890.',
            'tanggal_bergabung.before_or_equal' => 'Tanggal bergabung tidak boleh melebihi hari ini.',
        ];
    }
}
