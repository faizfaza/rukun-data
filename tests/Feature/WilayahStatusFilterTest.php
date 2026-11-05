<?php

namespace Tests\Feature;

use App\Filament\Resources\WilayahResource\Pages\ListWilayah;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WilayahStatusFilterTest extends TestCase
{
    use FixturesRukunData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sensitif.hmac_key' => str_repeat('t', 32)]);
    }

    public function test_filter_status_aktif_dan_nonaktif_berjalan_dengan_benar(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super', 'superadmin', null);
        $this->actingAs($superadmin);

        // Buat satu RW nonaktif di bawah desa
        $rwNonaktif = Wilayah::create([
            'kode' => 'T.1.1.1.1.99',
            'nama' => 'RW Nonaktif',
            'level' => 'rw',
            'parent_id' => $w['desa']->id,
            'is_active' => false,
        ]);

        // Filter aktif pada level RW
        Livewire::withQueryParams(['parent' => $w['desa']->id, 'status' => 'aktif'])
            ->test(ListWilayah::class, [
                'parentId' => $w['desa']->id,
                'statusFilter' => 'aktif',
            ])
            ->assertSee($w['rw']->nama)
            ->assertDontSee($rwNonaktif->nama);

        // Filter nonaktif pada level RW
        Livewire::withQueryParams(['parent' => $w['desa']->id, 'status' => 'nonaktif'])
            ->test(ListWilayah::class, [
                'parentId' => $w['desa']->id,
                'statusFilter' => 'nonaktif',
            ])
            ->assertSee($rwNonaktif->nama)
            ->assertDontSee($w['rw']->nama);

        // Atur ulang filter mengembalikan statusFilter kosong
        Livewire::withQueryParams(['parent' => $w['desa']->id, 'status' => 'nonaktif'])
            ->test(ListWilayah::class, [
                'parentId' => $w['desa']->id,
                'statusFilter' => 'nonaktif',
            ])
            ->call('resetWilayahFilter')
            ->assertSet('statusFilter', '');
    }

    public function test_filter_status_down_menampilkan_wilayah_dengan_induk_nonaktif(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super', 'superadmin', null);
        $this->actingAs($superadmin);

        // Nonaktifkan RW
        $w['rw']->update(['is_active' => false]);

        // RT 1 tetap is_active = true, tetapi operasional = down karena induk nonaktif
        $this->assertTrue($w['rt1']->fresh()->is_active);
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());

        // Buka daftar di bawah RW dengan filter down
        Livewire::withQueryParams(['parent' => $w['rw']->id, 'status' => 'down'])
            ->test(ListWilayah::class, [
                'parentId' => $w['rw']->id,
                'statusFilter' => 'down',
            ])
            ->assertSee($w['rt1']->nama)
            ->assertSee($w['rt2']->nama);
    }

    public function test_cascading_filter_wilayah_berurutan(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super', 'superadmin', null);
        $this->actingAs($superadmin);

        Livewire::test(ListWilayah::class)
            ->set('filterProvinsi', $w['provinsi']->id)
            ->assertSet('parentId', $w['provinsi']->id)
            ->assertSee($w['kabupaten']->nama)
            ->set('filterKabkota', $w['kabupaten']->id)
            ->assertSet('parentId', $w['kabupaten']->id)
            ->assertSee($w['kecamatan']->nama);
    }

    public function test_filter_rw_dan_rt_bebas_tanpa_harus_berurutan(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super', 'superadmin', null);
        $this->actingAs($superadmin);

        // Buat wilayah lain di provinsi lain yang juga punya RW 001
        $prov2 = Wilayah::create(['kode' => 'P2', 'nama' => 'Provinsi Lain', 'level' => 'provinsi', 'parent_id' => $w['negara']->id]);
        $kab2 = Wilayah::create(['kode' => 'P2.1', 'nama' => 'Kabupaten Lain', 'level' => 'kabupaten_kota', 'parent_id' => $prov2->id]);
        $kec2 = Wilayah::create(['kode' => 'P2.1.1', 'nama' => 'Kecamatan Lain', 'level' => 'kecamatan', 'parent_id' => $kab2->id]);
        $desa2 = Wilayah::create(['kode' => 'P2.1.1.1', 'nama' => 'Desa Lain', 'level' => 'desa', 'parent_id' => $kec2->id]);
        $rw2 = Wilayah::create(['kode' => 'P2.1.1.1.1', 'nama' => 'RW 001', 'level' => 'rw', 'parent_id' => $desa2->id]);

        // Cukup isi filterRw = 'RW 001' tanpa isi provinsi/kab/kec/desa
        Livewire::test(ListWilayah::class)
            ->set('filterRw', 'RW 001')
            ->assertSee($w['rw']->nama)
            ->assertSee($rw2->nama);
    }
}
