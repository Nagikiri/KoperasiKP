<?php

namespace App\Http\Requests;

use App\Models\Anggota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanAnggotaRequest extends FormRequest
{
    /**
     * Nama query parameter filter laporan per anggota, juga key kontrak `$filter`.
     */
    public const FILTER = ['anggota_id', 'status_anggota', 'tanggal_mulai', 'tanggal_selesai'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * BR-15 (revisi R6): semua filter opsional. anggota_id kosong = semua anggota;
     * status_anggota hanya berlaku saat semua anggota.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'anggota_id' => ['nullable', 'integer', 'exists:anggota,id'],
            'status_anggota' => ['nullable', Rule::in([Anggota::STATUS_AKTIF, Anggota::STATUS_NONAKTIF])],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'status_anggota.in' => 'Status anggota harus aktif atau nonaktif.',
        ];
    }

    /**
     * Kontrak `$filter` (00 §4.7) dari query string apa pun (dipakai juga oleh pratinjau).
     *
     * @return array<string, string|null>
     */
    public static function filterDari(Request $request): array
    {
        $filter = [];

        foreach (self::FILTER as $key) {
            $nilai = $request->query($key);
            $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';
            $filter[$key] = $nilai === '' ? null : $nilai;
        }

        return $filter;
    }
}
