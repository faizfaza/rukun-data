<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8: kolom identitas terenkripsi (TEXT); tanggal/tempat lahir dan
// FK ref dibiarkan plain agar dashboard tanpa dekripsi. kepala_lock berisi
// id_keluarga hanya pada baris kepala keluarga, NULL pada baris lain, dan
// UNIQUE sehingga database menegakkan tepat satu kepala per keluarga.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_keluarga')->constrained('keluarga')->cascadeOnDelete();
            $table->text('nik');
            $table->char('nik_hash', 64)->unique();
            $table->text('nama_lengkap');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->enum('status_kawin', ['belum_kawin', 'kawin', 'cerai_hidup', 'cerai_mati']);
            $table->foreignId('id_agama')->constrained('ref_agama');
            $table->foreignId('id_pendidikan')->constrained('ref_pendidikan');
            $table->foreignId('id_pekerjaan')->constrained('ref_pekerjaan');
            $table->foreignId('id_hubungan')->constrained('ref_hubungan');
            $table->text('nama_ayah')->nullable();
            $table->text('nama_ibu')->nullable();
            $table->text('penghasilan_bulanan')->nullable();
            $table->unsignedBigInteger('kepala_lock')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota');
    }
};
