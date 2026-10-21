<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// PRD §14.4: admin_rt tidak dapat melihat data RT lain, baik lewat daftar
// maupun akses URL langsung (findOrFail + global scope = 404). admin_rw
// melihat seluruh RT di bawah RW-nya; superadmin melihat semua.
class WilayahIsolationTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_admin_rt_hanya_melihat_wilayahnya(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $rt1 = $this->makeUser('rt1', 'admin_rt', $w['rt1']->id);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $superadmin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $superadmin->id);

        $this->actingAs($rt1);

        $this->assertSame(1, Keluarga::count());
        $this->assertSame(1, Anggota::count());
        $this->assertSame('1111111111111111', Keluarga::first()->no_kk);
    }

    public function test_akses_langsung_data_rt_lain_gagal(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $rt1 = $this->makeUser('rt1', 'admin_rt', $w['rt1']->id);

        $asing = $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $superadmin->id);

        $this->actingAs($rt1);

        $this->expectException(ModelNotFoundException::class);

        Keluarga::findOrFail($asing->id);
    }

    public function test_admin_rw_melihat_semua_rt_dan_superadmin_melihat_semua(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $superadmin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $superadmin->id);

        $this->actingAs($adminRw);
        $this->assertSame(2, Keluarga::count());

        $this->actingAs($superadmin);
        $this->assertSame(2, Keluarga::count());
    }
}
