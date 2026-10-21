<?php

namespace Tests\Feature;

use App\Filament\Pages\JelajahWilayah;
use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Fase 3.10: titik awal per peran, breadcrumb, agregasi, akses, tombol tambah,
// dan pencarian persis. Titik awal diuji lewat HTTP (perilaku nyata), sisanya
// lewat komponen Livewire dengan parameter node.
class JelajahWilayahTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_titik_awal_superadmin_dari_negara(): void
    {
        $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $this->get('/admin/jelajah')
            ->assertOk()
            ->assertSee('Negara Uji')
            ->assertSee('Provinsi Uji')
            ->assertDontSee('RT 001');
    }

    public function test_titik_awal_admin_rw_dari_rw(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rw', 'admin_rw', $w['rw']->id));

        $this->get('/admin/jelajah')
            ->assertOk()
            ->assertSee('RW 001')
            ->assertSee('RT 001')
            // Leluhur di atas RW-nya tidak tampil.
            ->assertDontSee('Desa Uji')
            ->assertDontSee('Negara Uji');
    }

    public function test_titik_awal_admin_rt_dari_rt(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->get('/admin/jelajah')
            ->assertOk()
            ->assertSee('RT 001')
            ->assertSee(SensitiveData::mask('6371010101000001'), false)
            ->assertDontSee('Negara Uji');
    }

    public function test_breadcrumb_tidak_menampilkan_leluhur_di_atas_titik_awal(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rw', 'admin_rw', $w['rw']->id));

        $c = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['rt2']]);
        $nama = collect($c->instance()->breadcrumbsRantai())->pluck('nama')->all();

        $this->assertSame(['RW 001', 'RT 002'], $nama);
        $this->assertNotContains('Negara Uji', $nama);
        $this->assertNotContains('Desa Uji', $nama);
    }

    public function test_jumlah_anak_dan_kk_benar(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [['nik' => '1111111111111111', 'hubungan' => 1]], $sa->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
            ['nik' => '2222222222222223', 'hubungan' => 3],
        ], $sa->id);
        $this->makeKeluarga($w['rt2']->id, '3333333333333333', [['nik' => '3333333333333333', 'hubungan' => 1]], $sa->id);

        $this->actingAs($sa);

        $c = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['rw']]);
        $ringkasan = $c->instance()->ringkasan();

        $this->assertSame(2, $ringkasan['anak']);
        $this->assertSame(3, $ringkasan['kk']);
        $this->assertSame(4, $ringkasan['anggota']);

        $peta = $c->instance()->kkKeturunan();
        $this->assertSame(1, $peta[$w['rt1']->id]);
        $this->assertSame(2, $peta[$w['rt2']->id]);
    }

    public function test_url_di_luar_subtree_404(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->get('/admin/jelajah/'.$w['rt2']->id)->assertNotFound();
    }

    public function test_tombol_tambah_untuk_admin_rt(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->get('/admin/jelajah')->assertOk()->assertSee('Tambah keluarga');
    }

    public function test_tombol_tambah_tidak_untuk_admin_rw(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rw', 'admin_rw', $w['rw']->id));

        // Node RT milik RW-nya: boleh dilihat, tetapi tanpa tombol tambah.
        $c = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['rt1']]);
        $this->assertSame('rt', $c->instance()->wilayah->level);
        $this->assertFalse($c->instance()->bolehTambahKeluarga());

        $this->get('/admin/jelajah')->assertOk()->assertDontSee('Tambah keluarga');
    }

    public function test_pencarian_no_kk_persis(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($sa);

        Livewire::test(JelajahWilayah::class, ['wilayah' => $w['negara']])
            ->call('cariPersis', '6371010101000001')
            ->assertRedirect(KeluargaResource::getUrl('view', ['record' => $keluarga]));
    }

    public function test_pencarian_nik_persis(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);
        $anggota = $keluarga->anggota()->first();

        $this->actingAs($sa);

        Livewire::test(JelajahWilayah::class, ['wilayah' => $w['negara']])
            ->call('cariPersis', '6371010101800001')
            ->assertRedirect(AnggotaResource::getUrl('view', ['record' => $anggota]));
    }

    public function test_pencarian_menghormati_scope_wilayah(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        // KK ada di RT lain → tidak ditemukan, tidak ada redirect.
        Livewire::test(JelajahWilayah::class, ['wilayah' => $w['rt1']])
            ->call('cariPersis', '2222222222222222')
            ->assertNoRedirect();
    }
}
