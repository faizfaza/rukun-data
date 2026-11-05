<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\WilayahResource\Pages\EditWilayah;
use App\Models\Wilayah;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WilayahHierarchyActiveTest extends TestCase
{
    use FixturesRukunData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sensitif.hmac_key' => str_repeat('t', 32)]);
    }

    public function test_rt_level_disabled_only_affects_that_rt(): void
    {
        $w = $this->seedDasar();

        // Nonaktifkan hanya RT 001
        $w['rt1']->update(['is_active' => false]);

        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt1']->fresh()->is_active);

        // RT 002, RW, dan level di atasnya tetap aktif
        $this->assertTrue($w['rt2']->fresh()->aktifEfektif());
        $this->assertTrue($w['rw']->fresh()->aktifEfektif());
        $this->assertTrue($w['desa']->fresh()->aktifEfektif());
        $this->assertTrue($w['kabupaten']->fresh()->aktifEfektif());
    }

    public function test_rw_level_disabled_affects_rw_and_all_its_rts(): void
    {
        $w = $this->seedDasar();

        // Nonaktifkan RW
        $w['rw']->update(['is_active' => false]);

        $this->assertFalse($w['rw']->fresh()->aktifEfektif());

        // Semua RT di bawah RW ini ikut down
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt2']->fresh()->aktifEfektif());

        // Level di atas RW (desa, kecamatan, kabupaten) tetap aktif
        $this->assertTrue($w['desa']->fresh()->aktifEfektif());
        $this->assertTrue($w['kecamatan']->fresh()->aktifEfektif());
        $this->assertTrue($w['kabupaten']->fresh()->aktifEfektif());
    }

    public function test_kabupaten_level_disabled_affects_entire_tree_under_it(): void
    {
        $w = $this->seedDasar();

        // Buat kabupaten lain beserta RW dan RT-nya untuk pembanding
        $kabupatenLain = Wilayah::create(['kode' => 'T.1.2', 'nama' => 'Kabupaten Lain', 'level' => 'kabupaten_kota', 'parent_id' => $w['provinsi']->id]);
        $kecamatanLain = Wilayah::create(['kode' => 'T.1.2.1', 'nama' => 'Kecamatan Lain', 'level' => 'kecamatan', 'parent_id' => $kabupatenLain->id]);
        $desaLain = Wilayah::create(['kode' => 'T.1.2.1.1', 'nama' => 'Desa Lain', 'level' => 'desa', 'tipe' => 'desa', 'parent_id' => $kecamatanLain->id]);
        $rwLain = Wilayah::create(['kode' => 'T.1.2.1.1.1', 'nama' => 'RW Lain', 'level' => 'rw', 'parent_id' => $desaLain->id]);
        $rtLain = Wilayah::create(['kode' => 'T.1.2.1.1.1.1', 'nama' => 'RT Lain', 'level' => 'rt', 'parent_id' => $rwLain->id]);

        // Nonaktifkan Kabupaten Uji
        $w['kabupaten']->update(['is_active' => false]);

        $this->assertFalse($w['kabupaten']->fresh()->aktifEfektif());
        $this->assertFalse($w['kecamatan']->fresh()->aktifEfektif());
        $this->assertFalse($w['desa']->fresh()->aktifEfektif());
        $this->assertFalse($w['rw']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt2']->fresh()->aktifEfektif());

        // Pohon di kabupaten lain tidak terdampak (tetap aktif)
        $this->assertTrue($kabupatenLain->fresh()->aktifEfektif());
        $this->assertTrue($kecamatanLain->fresh()->aktifEfektif());
        $this->assertTrue($desaLain->fresh()->aktifEfektif());
        $this->assertTrue($rwLain->fresh()->aktifEfektif());
        $this->assertTrue($rtLain->fresh()->aktifEfektif());
    }

    public function test_user_cannot_access_panel_when_assigned_wilayah_is_down(): void
    {
        $w = $this->seedDasar();
        $adminRt1 = $this->makeUser('admin-rt1', 'admin_rt', $w['rt1']->id);
        $adminRt2 = $this->makeUser('admin-rt2', 'admin_rt', $w['rt2']->id);
        $adminRw = $this->makeUser('admin-rw', 'admin_rw', $w['rw']->id);
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);

        $panel = Filament::getCurrentOrDefaultPanel();

        // Awalnya semua dapat mengakses panel
        $this->assertTrue($adminRt1->canAccessPanel($panel));
        $this->assertTrue($adminRt2->canAccessPanel($panel));
        $this->assertTrue($adminRw->canAccessPanel($panel));
        $this->assertTrue($superadmin->canAccessPanel($panel));

        // 1. Nonaktifkan RT 001: hanya admin_rt1 yang down
        $w['rt1']->update(['is_active' => false]);
        $this->assertFalse($adminRt1->fresh()->canAccessPanel($panel));
        $this->assertTrue($adminRt2->fresh()->canAccessPanel($panel));
        $this->assertTrue($adminRw->fresh()->canAccessPanel($panel));
        $this->assertTrue($superadmin->fresh()->canAccessPanel($panel));

        // 2. Aktifkan kembali RT 001, lalu nonaktifkan RW: admin_rw dan seluruh admin_rt down
        $w['rt1']->update(['is_active' => true]);
        $w['rw']->update(['is_active' => false]);
        $this->assertFalse($adminRt1->fresh()->canAccessPanel($panel));
        $this->assertFalse($adminRt2->fresh()->canAccessPanel($panel));
        $this->assertFalse($adminRw->fresh()->canAccessPanel($panel));
        $this->assertTrue($superadmin->fresh()->canAccessPanel($panel));

        // 3. Login attempt oleh admin yang wilayahnya down ditolak dengan pesan yang informatif
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin-rt1', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasErrors(['data.username']);
        $this->assertGuest();
    }

    public function test_edit_wilayah_form_toggles_is_active(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($superadmin);

        Livewire::test(EditWilayah::class, ['record' => $w['kecamatan']->id])
            ->assertFormSet(['is_active' => true])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($w['kecamatan']->fresh()->is_active);
        $this->assertFalse($w['kecamatan']->fresh()->aktifEfektif());
        $this->assertFalse($w['desa']->fresh()->aktifEfektif());
        $this->assertFalse($w['rw']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());
    }

    public function test_edit_wilayah_header_action_toggles_is_active(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($superadmin);

        Livewire::test(EditWilayah::class, ['record' => $w['rt1']->id])
            ->callAction('aktivasi');

        $this->assertFalse($w['rt1']->fresh()->is_active);
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());

        // Panggil lagi untuk mengaktifkan kembali
        Livewire::test(EditWilayah::class, ['record' => $w['rt1']->id])
            ->callAction('aktivasi');

        $this->assertTrue($w['rt1']->fresh()->is_active);
        $this->assertTrue($w['rt1']->fresh()->aktifEfektif());
    }
}

