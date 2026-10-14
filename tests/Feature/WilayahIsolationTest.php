<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// PRD §14.4: petugas RT tidak dapat melihat data RT lain, baik lewat daftar
// maupun akses URL langsung (findOrFail + global scope = 404).
class WilayahIsolationTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_petugas_rt_hanya_melihat_wilayahnya(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);
        $rt1 = $this->makeUser('rt1', 'rt_rw', $w['rt1']->id);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $admin->id);

        $this->actingAs($rt1);

        $this->assertSame(1, Keluarga::count());
        $this->assertSame(1, Anggota::count());
        $this->assertSame('1111111111111111', Keluarga::first()->no_kk);
    }

    public function test_akses_langsung_data_rt_lain_gagal(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);
        $rt1 = $this->makeUser('rt1', 'rt_rw', $w['rt1']->id);

        $asing = $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $admin->id);

        $this->actingAs($rt1);

        $this->expectException(ModelNotFoundException::class);

        Keluarga::findOrFail($asing->id);
    }

    public function test_desa_melihat_gabungan_admin_melihat_semua(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);
        $desa = $this->makeUser('desa', 'desa', $w['desa']->id);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $admin->id);

        $this->actingAs($desa);
        $this->assertSame(2, Keluarga::count());

        $this->actingAs($admin);
        $this->assertSame(2, Keluarga::count());
    }
}
