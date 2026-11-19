<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'admin_desa', 'admin_rw', 'admin_rt') DEFAULT 'admin_rt'");
        }

        if (! Schema::hasColumn('wilayah', 'pengaturan')) {
            Schema::table('wilayah', function (Blueprint $table): void {
                $table->json('pengaturan')->nullable()->after('versi_sumber');
            });
        }

        if (! Schema::hasTable('validasi_perubahan')) {
            Schema::create('validasi_perubahan', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
                $table->string('peran_pengaju', 30)->nullable();
                $table->string('aksi', 30);
                $table->string('nama_tabel', 50);
                $table->unsignedBigInteger('id_record');
                $table->unsignedBigInteger('id_keluarga')->nullable()->index();
                $table->foreignId('id_wilayah_rt')->nullable()->constrained('wilayah')->nullOnDelete();
                $table->foreignId('id_wilayah_rw')->nullable()->constrained('wilayah')->nullOnDelete();
                $table->foreignId('id_wilayah_desa')->nullable()->constrained('wilayah')->nullOnDelete();
                $table->foreignId('id_wilayah_tujuan')->nullable()->constrained('wilayah')->nullOnDelete();
                $table->string('status', 30)->default('menunggu_rw')->index();
                $table->text('ringkasan')->nullable();
                $table->text('catatan_pengaju')->nullable();
                $table->longText('payload')->nullable();
                $table->foreignId('id_verifikator_rw')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('waktu_verifikasi_rw')->nullable();
                $table->text('catatan_rw')->nullable();
                $table->foreignId('id_verifikator_desa')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('waktu_verifikasi_desa')->nullable();
                $table->text('catatan_desa')->nullable();
                $table->timestamps();

                $table->index(['nama_tabel', 'id_record']);
                $table->index(['id_wilayah_rw', 'status']);
                $table->index(['id_wilayah_desa', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_perubahan');

        if (Schema::hasColumn('wilayah', 'pengaturan')) {
            Schema::table('wilayah', function (Blueprint $table): void {
                $table->dropColumn('pengaturan');
            });
        }
    }
};

