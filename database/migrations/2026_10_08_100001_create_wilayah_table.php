<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keputusan 1: wilayah berjenjang nasional.
// negara -> provinsi -> kabupaten_kota -> kecamatan -> desa -> (dusun opsional) -> rw -> rt
// Kolom `path` menyimpan jalur dari akar (contoh "/1/12/45/") agar leluhur dan
// keturunan bisa diambil lewat LIKE prefix, bukan rekursi.
// UNIQUE lama (kode, level) diganti UNIQUE (parent_id, kode): kode RW "01" atau
// RT "001" berulang di setiap desa, jadi unik global akan bentrok di skala nasional.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32);
            $table->string('nama');
            $table->enum('level', [
                'negara', 'provinsi', 'kabupaten_kota', 'kecamatan',
                'desa', 'dusun', 'rw', 'rt',
            ]);
            // Hanya terisi untuk level desa: 'desa' atau 'kelurahan'.
            $table->string('tipe', 16)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('wilayah')->restrictOnDelete();
            // Jalur dari akar, selalu diakhiri "/" (contoh "/1/12/45/").
            $table->string('path', 255)->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'kode']);
            $table->index('parent_id');
            $table->index('level');
            // utf8mb4 + MySQL 8 (innodb_large_prefix) menampung indeks penuh
            // 255 karakter (<= 3072 byte), jadi tidak perlu prefix index.
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
