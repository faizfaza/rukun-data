<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8: tabel referensi. Nilai master diisi seeder; id hubungan kepala
// keluarga ditetapkan tetap (1) agar aturan satu-kepala konsisten.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_agama', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
        });

        Schema::create('ref_pendidikan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
        });

        Schema::create('ref_pekerjaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
        });

        Schema::create('ref_hubungan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_hubungan');
        Schema::dropIfExists('ref_pekerjaan');
        Schema::dropIfExists('ref_pendidikan');
        Schema::dropIfExists('ref_agama');
    }
};
