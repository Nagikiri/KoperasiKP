<?php

namespace App\Http\Requests;

use App\Models\Anggota;
use App\Models\TransaksiSimpanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaporanKeseluruhanRequest extends FormRequest
{
    /**
     * Nama query parameter filter laporan keseluruhan (BR-20), juga key kontrak `$filter`.
     */
    public const FILTER = ['tanggal_mulai', 'tanggal_selesai', 'jenis_simpanan_id', 'jenis_transaksi', 'status_anggota'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * BR-20: semua filter opsional.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jenis_simpanan_id' => ['nullable', 'integer', 'exists:jenis_simpanan,id'],
            'jenis_transaksi' => ['nullable', Rule::in([TransaksiSimpanan::SETOR, TransaksiSimpanan::TARIK])],
            'status_anggota' => ['nullable', Rule::in([Anggota::STATUS_AKTIF, Anggota::STATUS_NONAKTIF])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'jenis_transaksi.in' => 'Jenis transaksi harus setor atau tarik.',
            'status_anggota.in' => 'Status anggota harus aktif atau nonaktif.',
        ];
    }

    /**
     * Kontrak `$filter` (00 §4.7): key = nama query parameter, nilai string|null.
     *
     * @return array<string, string|null>
     */
    public function filter(): array
    {
        $filter = [];

        foreach (self::FILTER as $key) {
            $nilai = $this->query($key);
            $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';
            $filter[$key] = $nilai === '' ? null : $nilai;
        }

        return $filter;
    }
}
