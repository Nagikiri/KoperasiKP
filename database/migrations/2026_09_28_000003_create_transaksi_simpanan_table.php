<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_simpanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggota_id')->constrained('anggota')->onDelete('restrict');
            $table->foreignId('jenis_simpanan_id')->constrained('jenis_simpanan')->onDelete('restrict');
            $table->foreignId('staff_id')->constrained('users')->onDelete('restrict');
            $table->enum('jenis_transaksi', ['setor', 'tarik']);
            $table->decimal('jumlah', 15, 2)->unsigned();
            $table->date('tanggal_transaksi');
            $table->decimal('saldo_akhir', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_simpanan');
    }
};
