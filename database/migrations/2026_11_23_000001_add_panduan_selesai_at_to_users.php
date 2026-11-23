<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('panduan_selesai_at')->nullable());

        // Akun yang sudah ada tetap dapat langsung memakai panel.
        DB::table('users')->update(['panduan_selesai_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('panduan_selesai_at'));
    }
};
