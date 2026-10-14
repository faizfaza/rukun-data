<?php

use App\Models\Anggota;
use App\Support\SensitiveData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indeks butir nama: pendamping nama_lengkap yang terenkripsi, berisi hash
// HMAC tiap kata nama sehingga pencarian nama bisa jalan di SQL tanpa
// menyimpan nama terbaca (sejalan dengan nik_hash / no_kk_hash).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            $table->text('nama_tokens')->nullable()->after('nama_lengkap');
        });

        // Isi ulang indeks untuk baris yang sudah ada.
        Anggota::withoutGlobalScopes()
            ->withTrashed()
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $row->forceFill([
                        'nama_tokens' => implode(' ', SensitiveData::nameTokens((string) $row->nama_lengkap)),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            $table->dropColumn('nama_tokens');
        });
    }
};
