<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8: satu baris per keluarga per tahun; kategori terenkripsi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekonomi_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_keluarga')->constrained('keluarga')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun_data');
            $table->text('kategori_ekonomi');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id_keluarga', 'tahun_data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekonomi_keluarga');
    }
};
