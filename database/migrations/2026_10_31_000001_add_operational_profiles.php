<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wilayah', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->string('sumber')->default('lokal');
            $t->string('kode_sumber')->nullable();
            $t->string('versi_sumber')->nullable();
            $t->unique(['sumber', 'kode_sumber']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->text('identitas')->nullable());
        Schema::table('anggota', fn (Blueprint $t) => $t->text('profil')->nullable());
    }

    public function down(): void
    {
        Schema::table('anggota', fn (Blueprint $t) => $t->dropColumn('profil'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('identitas'));
        Schema::table('wilayah', function (Blueprint $t) {
            $t->dropUnique(['sumber', 'kode_sumber']);
            $t->dropColumn(['is_active', 'sumber', 'kode_sumber', 'versi_sumber']);
        });
    }
};
