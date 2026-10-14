<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §4.7: users memakai username untuk login (email bawaan Laravel tetap
// ada tapi tidak dipakai login), kolom role enum tanpa paket tambahan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->enum('role', ['rt_rw', 'desa', 'admin'])->default('rt_rw')->after('password');
            $table->foreignId('id_wilayah')->nullable()->after('role')->constrained('wilayah')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_wilayah');
            $table->dropColumn(['username', 'role']);
        });
    }
};
