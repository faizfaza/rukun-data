<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah alamat_ktp dan is_locked pada keluarga
        Schema::table('keluarga', function (Blueprint $table) {
            $table->text('alamat_ktp')->nullable()->after('alamat');
            $table->boolean('is_locked')->default(false)->after('kode_pos');
        });

        // 2. Tambah status_kependudukan pada anggota (menetap, pindah, meninggal)
        Schema::table('anggota', function (Blueprint $table) {
            $table->string('status_kependudukan', 20)->default('menetap')->after('status_kawin');
        });

        // 3. Tabel pendataan aset / fasilitas keluarga (opsional)
        Schema::create('aset_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_keluarga')->constrained('keluarga')->cascadeOnDelete();
            $table->string('jenis_aset', 100);
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_keluarga');

        Schema::table('anggota', function (Blueprint $table) {
            $table->dropColumn('status_kependudukan');
        });

        Schema::table('keluarga', function (Blueprint $table) {
            $table->dropColumn(['alamat_ktp', 'is_locked']);
        });
    }
};

