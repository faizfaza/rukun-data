<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kinerja: scope operasional menyaring leluhur nonaktif lewat subquery
// `is_active = false`. Tanpa indeks, subquery itu memindai seluruh tabel
// wilayah untuk setiap baris kandidat (O(n^2)) sehingga pemuatan opsi
// wilayah di form bisa melewati batas waktu eksekusi.
return new class extends Migration
{
    private const INDEX = 'wilayah_is_active_index';

    public function up(): void
    {
        if (! Schema::hasIndex('wilayah', self::INDEX)) {
            Schema::table('wilayah', function (Blueprint $table): void {
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('wilayah', self::INDEX)) {
            Schema::table('wilayah', function (Blueprint $table): void {
                $table->dropIndex(['is_active']);
            });
        }
    }
};
