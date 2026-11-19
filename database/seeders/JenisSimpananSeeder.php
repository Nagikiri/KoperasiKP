<?php

namespace Database\Seeders;

use App\Models\JenisSimpanan;
use Illuminate\Database\Seeder;

class JenisSimpananSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama_jenis' => JenisSimpanan::POKOK,
                'keterangan' => 'Setoran awal saat jadi anggota',
            ],
            [
                'nama_jenis' => JenisSimpanan::WAJIB,
                'keterangan' => 'Setoran rutin tiap bulan',
            ],
            [
                'nama_jenis' => JenisSimpanan::SUKARELA,
                'keterangan' => 'Bisa disetor kapan saja',
            ],
        ];

        foreach ($data as $item) {
            JenisSimpanan::updateOrCreate(
                ['nama_jenis' => $item['nama_jenis']],
                ['keterangan' => $item['keterangan']]
            );
        }
    }
}
