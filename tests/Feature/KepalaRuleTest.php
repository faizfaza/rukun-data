<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\RefHubungan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// PRD §8.5: tepat satu kepala keluarga per keluarga, dijaga aplikasi dan
// UNIQUE database (kepala_lock).
class KepalaRuleTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_kepala_kedua_ditolak_database(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);

        $this->assertSame($keluarga->id, Anggota::first()->kepala_lock);

        $this->expectException(QueryException::class);

        Anggota::create([
            'id_keluarga' => $keluarga->id,
            'nik' => '2222222222222222',
            'nama_lengkap' => 'Kepala Kedua',
            'tempat_lahir' => 'Uji',
            'tanggal_lahir' => '1992-02-02',
            'jenis_kelamin' => 'L',
            'status_kawin' => 'kawin',
            'id_agama' => 1,
            'id_pendidikan' => 1,
            'id_pekerjaan' => 1,
            'id_hubungan' => RefHubungan::KEPALA,
            'created_by' => $admin->id,
        ]);
    }
}
