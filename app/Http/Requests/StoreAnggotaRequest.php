<?php

namespace App\Http\Requests;

use App\Models\Anggota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreAnggotaRequest extends FormRequest
{
    public const PESAN_NIK_TERDAFTAR = 'NIK ini sudah terdaftar atas anggota lain.';

    private ?Anggota $anggotaDuplikat = null;

    private bool $duplikatSudahDicek = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * BR-19: kiriman ganda dicek SEBELUM validasi, supaya kiriman kedua tidak
     * ditolak oleh aturan NIK unik. Jika duplikat, validasi dilewati dan
     * controller langsung mengarahkan ke anggota yang sudah tersimpan.
     */
    public function validateResolved(): void
    {
        if ($this->anggotaDuplikat() !== null) {
            return;
        }

        parent::validateResolved();
    }

    /**
     * Anggota yang sudah tersimpan dengan kode_referensi kiriman ini, jika ada.
     */
    public function anggotaDuplikat(): ?Anggota
    {
        if (! $this->duplikatSudahDicek) {
            $this->duplikatSudahDicek = true;

            $kode = $this->input('kode_referensi');

            if (is_string($kode) && Str::isUuid($kode)) {
                $this->anggotaDuplikat = Anggota::where('kode_referensi', $kode)->first();
            }
        }

        return $this->anggotaDuplikat;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kode_referensi' => ['required', 'uuid'],
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
            'nik.unique' => self::PESAN_NIK_TERDAFTAR,
            'no_telepon.regex' => 'Nomor telepon tidak valid. Contoh: 081234567890.',
            'tanggal_bergabung.before_or_equal' => 'Tanggal bergabung tidak boleh melebihi hari ini.',
        ];
    }
}
