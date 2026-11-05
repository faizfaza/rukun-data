<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\WilayahResource\Pages\CreateWilayah;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WilayahCascadingIndukTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private Wilayah $negara;
    private Wilayah $prov;
    private Wilayah $kab;
    private Wilayah $kec;
    private Wilayah $desa;
    private Wilayah $rw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'role' => Role::Superadmin,
            'username' => 'superadmin',
            'is_active' => true,
        ]);

        $this->negara = Wilayah::create(['nama' => 'Indonesia', 'kode' => 'ID', 'level' => 'negara']);
        $this->prov = Wilayah::create(['nama' => 'Jawa Barat', 'kode' => '32', 'level' => 'provinsi', 'parent_id' => $this->negara->id]);
        $this->kab = Wilayah::create(['nama' => 'Kabupaten Bandung', 'kode' => '32.04', 'level' => 'kabupaten_kota', 'parent_id' => $this->prov->id]);
        $this->kec = Wilayah::create(['nama' => 'Bojongsoang', 'kode' => '32.04.05', 'level' => 'kecamatan', 'parent_id' => $this->kab->id]);
        $this->desa = Wilayah::create(['nama' => 'Bojongsoang', 'kode' => '32.04.05.2001', 'level' => 'desa', 'parent_id' => $this->kec->id]);
        $this->rw = Wilayah::create(['nama' => 'RW 01', 'kode' => 'RW-01', 'level' => 'rw', 'parent_id' => $this->desa->id]);
    }

    public function test_buat_rw_dengan_urutan_induk_berjenjang(): void
    {
        $this->actingAs($this->superadmin);

        Livewire::test(CreateWilayah::class)
            ->fillForm([
                'level' => 'rw',
                'provinsi_id' => $this->prov->id,
                'kabkota_id' => $this->kab->id,
                'kecamatan_id' => $this->kec->id,
                'desa_id' => $this->desa->id,
                'dusun_id' => $this->desa->id, // Langsung di bawah desa
                'kode' => 'RW-02',
                'nama' => 'RW 02',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rwBaru = Wilayah::where('kode', 'RW-02')->first();
        $this->assertNotNull($rwBaru);
        $this->assertSame('rw', $rwBaru->level);
        $this->assertSame($this->desa->id, $rwBaru->parent_id);
    }

    public function test_buat_rt_dengan_urutan_induk_berjenjang(): void
    {
        $this->actingAs($this->superadmin);

        Livewire::test(CreateWilayah::class)
            ->fillForm([
                'level' => 'rt',
                'provinsi_id' => $this->prov->id,
                'kabkota_id' => $this->kab->id,
                'kecamatan_id' => $this->kec->id,
                'desa_id' => $this->desa->id,
                'rw_id' => $this->rw->id,
                'kode' => 'RT-05',
                'nama' => 'RT 05',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rtBaru = Wilayah::where('kode', 'RT-05')->first();
        $this->assertNotNull($rtBaru);
        $this->assertSame('rt', $rtBaru->level);
        $this->assertSame($this->rw->id, $rtBaru->parent_id);
    }
}

