<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8/F12: persetujuan menempel ke anggota. UI dicatat menyusul (P2);
// skema disediakan sekarang agar tidak ada migrasi susulan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persetujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_anggota')->constrained('anggota')->cascadeOnDelete();
            $table->string('nama_pemberi');
            $table->date('tanggal');
            $table->enum('status', ['aktif', 'dicabut'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persetujuan');
    }
};
