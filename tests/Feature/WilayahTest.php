<?php

namespace Tests\Feature;

use App\Filament\Resources\WilayahResource;
use App\Filament\Resources\WilayahResource\Pages\CreateWilayah;
use App\Filament\Resources\WilayahResource\Pages\ListWilayah;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

// Fase 1.6: hierarki, path, keunikan, aturan hapus, dan akses resource.
class WilayahTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_peta_induk_valid_per_level(): void
    {
        $this->assertSame([], Wilayah::indukValid('negara'));
        $this->assertSame(['negara'], Wilayah::indukValid('provinsi'));
        $this->assertSame(['provinsi'], Wilayah::indukValid('kabupaten_kota'));
        $this->assertSame(['kabupaten_kota'], Wilayah::indukValid('kecamatan'));
        $this->assertSame(['kecamatan'], Wilayah::indukValid('desa'));
        $this->assertSame(['desa'], Wilayah::indukValid('dusun'));
        $this->assertSame(['dusun', 'desa'], Wilayah::indukValid('rw'));
        $this->assertSame(['rw'], Wilayah::indukValid('rt'));
    }

    public function test_induk_salah_level_ditolak(): void
    {
        $w = $this->seedDasar();

        $this->expectException(ValidationException::class);

        // RT tidak boleh berinduk desa.
        Wilayah::create(['kode' => 'X', 'nama' => 'Salah', 'level' => 'rt', 'parent_id' => $w['desa']->id]);
    }

    public function test_negara_tidak_boleh_punya_induk(): void
    {
        $w = $this->seedDasar();

        $this->expectException(ValidationException::class);

        Wilayah::create(['kode' => 'N2', 'nama' => 'Negara Lain', 'level' => 'negara', 'parent_id' => $w['negara']->id]);
    }

    public function test_selain_negara_wajib_punya_induk(): void
    {
        $this->expectException(ValidationException::class);

        Wilayah::create(['kode' => 'P', 'nama' => 'Provinsi Tanpa Induk', 'level' => 'provinsi']);
    }

    public function test_path_benar_setelah_dibuat(): void
    {
        $w = $this->seedDasar();

        $this->assertSame('/'.$w['negara']->id.'/', $w['negara']->fresh()->path);
        $this->assertSame($w['negara']->path.$w['provinsi']->id.'/', $w['provinsi']->fresh()->path);
        $this->assertSame($w['kecamatan']->path.$w['desa']->id.'/', $w['desa']->fresh()->path);
        $this->assertSame($w['desa']->path.$w['rw']->id.'/', $w['rw']->fresh()->path);
        $this->assertSame($w['rw']->path.$w['rt1']->id.'/', $w['rt1']->fresh()->path);
    }

    public function test_leluhur_dan_keturunan_lewat_path(): void
    {
        $w = $this->seedDasar();

        $this->assertSame(
            ['Negara Uji', 'Provinsi Uji', 'Kabupaten Uji', 'Kecamatan Uji', 'Desa Uji', 'RW 001'],
            $w['rt1']->ancestors()->pluck('nama')->all(),
        );

        $this->assertSame(
            ['RT 001', 'RT 002'],
            $w['rw']->descendants()->orderBy('kode')->pluck('nama')->all(),
        );
    }

    public function test_kode_rw_sama_di_dua_desa_diizinkan(): void
    {
        $w = $this->seedDasar();

        // Desa kedua di kecamatan yang sama.
        $desa2 = Wilayah::create([
            'kode' => 'T.1.1.1.2', 'nama' => 'Desa Uji 2', 'level' => 'desa', 'tipe' => 'desa', 'parent_id' => $w['kecamatan']->id,
        ]);

        // Kode RW dan RT yang sama persis di desa berbeda harus diizinkan.
        $rw2 = Wilayah::create(['kode' => 'T.1.1.1.1.1', 'nama' => 'RW 001 (desa 2)', 'level' => 'rw', 'parent_id' => $desa2->id]);
        $rt2 = Wilayah::create(['kode' => 'T.1.1.1.1.1.1', 'nama' => 'RT 001 (desa 2)', 'level' => 'rt', 'parent_id' => $rw2->id]);

        $this->assertNotSame($w['rw']->id, $rw2->id);
        $this->assertNotSame($w['rt1']->id, $rt2->id);
    }

    public function test_kode_ganda_dalam_satu_induk_ditolak(): void
    {
        $w = $this->seedDasar();

        $this->expectException(QueryException::class);

        Wilayah::create(['kode' => $w['rt1']->kode, 'nama' => 'RT kembar', 'level' => 'rt', 'parent_id' => $w['rw']->id]);
    }

    public function test_tipe_hanya_untuk_desa(): void
    {
        $w = $this->seedDasar();

        $this->expectException(ValidationException::class);

        Wilayah::create(['kode' => 'RZ', 'nama' => 'RW', 'level' => 'rw', 'tipe' => 'desa', 'parent_id' => $w['desa']->id]);
    }

    public function test_hapus_ditolak_jika_punya_anak_atau_keluarga(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        // RW punya anak (RT) → tidak boleh dihapus.
        $this->assertFalse(WilayahResource::canDelete($w['rw']));
        // RT tanpa anak dan tanpa keluarga → boleh dihapus.
        $this->assertTrue(WilayahResource::canDelete($w['rt1']));

        // Beri satu keluarga di RT1 → RT1 tidak boleh dihapus lagi.
        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [['nik' => '1111111111111111', 'hubungan' => 1]], $superadmin->id);
        $this->assertFalse(WilayahResource::canDelete($w['rt1']));
    }

    public function test_superadmin_mengakses_resource_wilayah(): void
    {
        $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);

        $this->actingAs($superadmin);
        $this->assertTrue(WilayahResource::canAccess());
        $this->get('/admin/wilayah')->assertOk();
    }

    public function test_admin_mengakses_resource_wilayah(): void
    {
        $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);

        $this->actingAs($admin);
        $this->assertTrue(WilayahResource::canAccess());
        $this->get('/admin/wilayah')->assertOk();
    }

    public function test_admin_rw_mengakses_resource_wilayah(): void
    {
        $w = $this->seedDasar();
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $this->actingAs($adminRw);
        $this->assertTrue(WilayahResource::canAccess());
        $this->get('/admin/wilayah')->assertOk();
    }

    public function test_admin_rt_ditolak_mengakses_resource_wilayah(): void
    {
        $w = $this->seedDasar();
        $rt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);

        $this->actingAs($rt);
        $this->assertFalse(WilayahResource::canAccess());
        $this->get('/admin/wilayah')->assertForbidden();
    }

    public function test_hirarki_wilayah_akar_hanya_menampilkan_negara(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        Livewire::test(ListWilayah::class)
            ->assertCanSeeTableRecords([$w['negara']])
            ->assertCanNotSeeTableRecords([$w['provinsi'], $w['kabupaten'], $w['rt1']]);
    }

    public function test_hirarki_drilldown_ke_anak_wilayah(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        // Buka Indonesia -> tampilkan provinsi di bawahnya
        Livewire::test(ListWilayah::class, ['parentId' => $w['negara']->id])
            ->assertCanSeeTableRecords([$w['provinsi']])
            ->assertCanNotSeeTableRecords([$w['negara'], $w['kabupaten'], $w['rt1']])
            // Buka Provinsi -> tampilkan kabupaten di bawahnya
            ->call('bukaWilayah', $w['provinsi']->id)
            ->assertCanSeeTableRecords([$w['kabupaten']])
            ->assertCanNotSeeTableRecords([$w['provinsi'], $w['kecamatan'], $w['rt1']])
            // Kembali ke induk -> kembali ke provinsi
            ->call('kembaliKeInduk')
            ->assertCanSeeTableRecords([$w['provinsi']])
            // Kembali ke akar -> kembali ke negara
            ->call('keAkar')
            ->assertCanSeeTableRecords([$w['negara']]);
    }

    public function test_mode_semua_menampilkan_seluruh_wilayah(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        Livewire::test(ListWilayah::class, ['mode' => 'semua'])
            ->assertCanSeeTableRecords([$w['negara'], $w['provinsi'], $w['kabupaten'], $w['rt1']]);
    }

    public function test_create_wilayah_otomatis_mengisi_level_dan_parent_dari_query(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        // Ketika berada di Jawa Barat (provinsi), tambah wilayah menyarankan kabupaten_kota
        Livewire::withQueryParams(['parent_id' => $w['provinsi']->id])
            ->test(CreateWilayah::class, ['parentId' => $w['provinsi']->id])
            ->assertSchemaStateSet([
                'level' => 'kabupaten_kota',
                'parent_id' => $w['provinsi']->id,
            ]);
    }

    public function test_pemisahan_tab_ketika_desa_memiliki_dusun_dan_rw_langsung(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        // Di bawah Desa Uji, tambahkan wilayah Dusun (sehingga desa punya Dusun Krajan dan RW 001 langsung)
        $dusun = Wilayah::create([
            'kode' => 'T.1.1.1.1.D1',
            'nama' => 'Dusun Krajan',
            'level' => 'dusun',
            'parent_id' => $w['desa']->id,
        ]);

        $rwLangsung = $w['rw']; // RW 001 yang menginduk langsung ke Desa Uji

        Livewire::test(ListWilayah::class, ['parentId' => $w['desa']->id])
            // Tab 'semua': melihat kedua turunan
            ->assertCanSeeTableRecords([$dusun, $rwLangsung])
            ->assertSee('RW Langsung')
            // Beralih ke tab 'dusun'
            ->set('activeTab', 'dusun')
            ->assertCanSeeTableRecords([$dusun])
            ->assertCanNotSeeTableRecords([$rwLangsung])
            // Beralih ke tab 'rw'
            ->set('activeTab', 'rw')
            ->assertCanSeeTableRecords([$rwLangsung])
            ->assertCanNotSeeTableRecords([$dusun]);
    }
}
