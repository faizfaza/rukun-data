<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arsip_snapshot_desa')) {
            Schema::create('arsip_snapshot_desa', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_wilayah_desa')->constrained('wilayah')->cascadeOnDelete();
                $table->string('kode_snapshot', 80)->index();
                $table->unsignedInteger('interval_jam')->default(36);
                $table->unsignedInteger('jumlah_keluarga')->default(0);
                $table->unsignedInteger('jumlah_warga')->default(0);
                $table->unsignedInteger('jumlah_menetap')->default(0);
                $table->unsignedInteger('jumlah_pindah')->default(0);
                $table->unsignedInteger('jumlah_perubahan')->default(0);
                $table->json('ringkasan_statistik')->nullable();
                $table->longText('data_snapshot')->nullable();
                $table->timestamps();

                $table->index(['id_wilayah_desa', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arsip_snapshot_desa');
    }
};

