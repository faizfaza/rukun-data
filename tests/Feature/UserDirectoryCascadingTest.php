<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDirectoryCascadingTest extends TestCase
{
    use FixturesRukunData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sensitif.hmac_key' => str_repeat('t', 32)]);
    }

    public function test_filter_admin_berdasarkan_status_dan_wilayah_berjenjang(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super', 'superadmin', null);
        $this->actingAs($superadmin);

        // Buat admin RW aktif dan admin RT nonaktif
        $adminRw = $this->makeUser('user-rw-1', 'admin_rw', $w['rw']->id);
        $adminRt = $this->makeUser('user-rt-1', 'admin_rt', $w['rt1']->id);
        $adminRt->update(['is_active' => false]);

        // Filter status aktif
        Livewire::test(ListUsers::class, ['statusFilter' => 'aktif'])
            ->assertSee($adminRw->name)
            ->assertDontSee($adminRt->name);

        // Filter status nonaktif
        Livewire::test(ListUsers::class, ['statusFilter' => 'nonaktif'])
            ->assertSee($adminRt->name)
            ->assertDontSee($adminRw->name);

        // Filter berjenjang: pilih provinsi
        Livewire::test(ListUsers::class)
            ->set('filterProvinsi', $w['provinsi']->id)
            ->assertSet('wilayah', $w['provinsi']->id)
            ->assertSee($adminRw->name)
            ->assertSee($adminRt->name);

        // Filter berjenjang: pilih RT spesifik
        Livewire::test(ListUsers::class)
            ->set('filterProvinsi', $w['provinsi']->id)
            ->set('filterKabkota', $w['kabupaten']->id)
            ->set('filterKecamatan', $w['kecamatan']->id)
            ->set('filterDesa', $w['desa']->id)
            ->set('filterRw', $w['rw']->id)
            ->set('filterRt', $w['rt1']->id)
            ->assertSet('wilayah', $w['rt1']->id)
            ->assertSee($adminRt->name)
            ->assertDontSee($adminRw->name);
    }

    public function test_filter_admin_bebas_rw_dan_rt_tanpa_harus_berurutan(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('super2', 'superadmin', null);
        $this->actingAs($superadmin);

        $adminRw1 = $this->makeUser('admin-rw-jabar', 'admin_rw', $w['rw']->id);
        $adminRt1 = $this->makeUser('admin-rt-jabar', 'admin_rt', $w['rt1']->id);

        // Hanya isi RW (string "01") tanpa Provinsi/Kabkota
        Livewire::test(ListUsers::class)
            ->set('filterRw', '01')
            ->assertSee($adminRw1->name)
            ->assertSee($adminRt1->name)
            ->assertSee('RW:')
            ->assertSee('01');

        // Hanya isi RT (string "01")
        Livewire::test(ListUsers::class)
            ->set('filterRt', '01')
            ->assertSee($adminRt1->name)
            ->assertDontSee($adminRw1->name)
            ->assertSee('RT:')
            ->assertSee('01');
    }
}

