<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8: wilayah berjenjang desa -> rw -> rt. Kolom kode mengikuti pola
// Kemendagri; UNIQUE(kode, level) menjaga kode tidak ganda dalam satu level.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32);
            $table->string('nama');
            $table->enum('level', ['desa', 'rw', 'rt']);
            $table->foreignId('parent_id')->nullable()->constrained('wilayah')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kode', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
