<?php

namespace Database\Factories;

use App\Models\Anggota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Anggota>
 */
class AnggotaFactory extends Factory
{
    protected $model = Anggota::class;

    /**
     * Urutan nomor anggota untuk factory. Prefiks tetap KSP-2000- supaya
     * tidak bentrok dengan generator yang memakai tahun berjalan.
     */
    protected static int $urutan = 0;

    public function definition(): array
    {
        static::$urutan++;

        return [
            'no_anggota' => 'KSP-2000-' . str_pad((string) static::$urutan, 4, '0', STR_PAD_LEFT),
            'nama' => fake()->name(),
            'nik' => fake()->unique()->numerify(fake()->numberBetween(1, 9) . '###############'),
            'alamat' => fake()->address(),
            'no_telepon' => '08' . fake()->numerify('##########'),
            'tanggal_bergabung' => '2020-01-01',
            'status' => Anggota::STATUS_AKTIF,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Anggota::STATUS_NONAKTIF,
        ]);
    }
}
