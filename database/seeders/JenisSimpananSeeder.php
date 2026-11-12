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
                'keterangan' => 'Dibayar sekali saat pertama kali mendaftar menjadi anggota',
            ],
            [
                'nama_jenis' => JenisSimpanan::WAJIB,
                'keterangan' => 'Dibayar rutin setiap periode tertentu',
            ],
            [
                'nama_jenis' => JenisSimpanan::SUKARELA,
                'keterangan' => 'Dapat disetor dan ditarik sewaktu-waktu',
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
