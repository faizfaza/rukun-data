<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8/F13: audit mencatat siapa-apa-kapan saja; nilai kolom terenkripsi
// maupun hash TIDAK PERNAH disimpan di sini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 20);
            $table->string('nama_tabel', 50);
            $table->unsignedBigInteger('id_record');
            $table->timestamp('waktu')->useCurrent();

            $table->index(['nama_tabel', 'id_record']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
