<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keputusan 4/6: tiga peran (superadmin, admin_rw, admin_rt). Login memakai
// username (email bawaan Laravel tetap ada tapi tidak dipakai login). Akun
// tidak dihapus, hanya dinonaktifkan lewat is_active. created_by mencatat
// siapa pembuat akun (peran di atasnya).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->enum('role', ['superadmin', 'admin_rw', 'admin_rt'])->default('admin_rt')->after('password');
            $table->foreignId('id_wilayah')->nullable()->after('role')->constrained('wilayah')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('id_wilayah');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_active');
            $table->dropConstrainedForeignId('id_wilayah');
            $table->dropColumn(['username', 'role']);
        });
    }
};
