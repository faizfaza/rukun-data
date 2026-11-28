<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifikasi_validasi')) {
            return;
        }

        Schema::create('notifikasi_validasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengguna')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_validasi')->nullable()->constrained('validasi_perubahan')->cascadeOnDelete();
            $table->string('jenis', 40)->index();
            $table->string('judul', 180);
            $table->text('pesan');
            $table->string('tautan', 255)->nullable();
            $table->timestamp('dibaca_pada')->nullable()->index();
            $table->timestamps();

            $table->index(['id_pengguna', 'dibaca_pada', 'created_at'], 'idx_notif_user_unread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_validasi');
    }
};
