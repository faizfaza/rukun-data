<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §8: no_kk dan alamat terenkripsi (kolom TEXT menampung ciphertext);
// pencarian dan cek duplikat lewat no_kk_hash (HMAC, UNIQUE).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_wilayah')->constrained('wilayah');
            $table->text('no_kk');
            $table->char('no_kk_hash', 64)->unique();
            $table->text('alamat');
            $table->string('kode_pos', 5);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keluarga');
    }
};
