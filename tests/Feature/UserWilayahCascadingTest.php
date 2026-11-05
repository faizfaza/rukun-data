<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WilayahPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserWilayahCascadingTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private Wilayah $negara;
    private Wilayah $prov;
    private Wilayah $kab;
    private Wilayah $kec;
    private Wilayah $desa;
    private Wilayah $rw;
    private Wilayah $rt;

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
        $this->rt = Wilayah::create(['nama' => 'RT 01', 'kode' => 'RT-01', 'level' => 'rt', 'parent_id' => $this->rw->id]);
    }

    public function test_wilayah_picker_can_search_by_ancestor_name(): void
    {
        $this->actingAs($this->superadmin);

        // Search 'jawa' with level 'rw' -> should find RW 01 in Jawa Barat
        $results = WilayahPicker::search('jawa', 'rw');
        $this->assertArrayHasKey($this->rw->id, $results);

        // Search 'bojongsoang' with level 'rw' -> should find RW 01
        $results2 = WilayahPicker::search('bojongsoang', 'rw');
        $this->assertArrayHasKey($this->rw->id, $results2);
    }

    public function test_create_admin_rw_with_cascading_selection(): void
    {
        $this->actingAs($this->superadmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'adminrw01',
                'name' => 'Admin RW 01',
                'password' => 'secret123',
                'role' => Role::AdminRw->value,
                'provinsi_id' => $this->prov->id,
                'kabkota_id' => $this->kab->id,
                'kecamatan_id' => $this->kec->id,
                'kelurahan_id' => $this->desa->id,
                'rw_id' => $this->rw->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('username', 'adminrw01')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::AdminRw, $user->role);
        $this->assertSame($this->rw->id, $user->id_wilayah);
    }

    public function test_create_admin_rt_with_cascading_selection(): void
    {
        $this->actingAs($this->superadmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'adminrt01',
                'name' => 'Admin RT 01',
                'password' => 'secret123',
                'role' => Role::AdminRt->value,
                'provinsi_id' => $this->prov->id,
                'kabkota_id' => $this->kab->id,
                'kecamatan_id' => $this->kec->id,
                'kelurahan_id' => $this->desa->id,
                'rw_id' => $this->rw->id,
                'rt_id' => $this->rt->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('username', 'adminrt01')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::AdminRt, $user->role);
        $this->assertSame($this->rt->id, $user->id_wilayah);
    }

    public function test_edit_user_hydrates_all_cascading_levels(): void
    {
        $this->actingAs($this->superadmin);

        $adminRt = User::factory()->create([
            'role' => Role::AdminRt,
            'id_wilayah' => $this->rt->id,
            'username' => 'rt_existing',
            'name' => 'RT Existing',
        ]);

        Livewire::test(EditUser::class, ['record' => $adminRt->id])
            ->assertFormSet([
                'provinsi_id' => $this->prov->id,
                'kabkota_id' => $this->kab->id,
                'kecamatan_id' => $this->kec->id,
                'kelurahan_id' => $this->desa->id,
                'rw_id' => $this->rw->id,
                'rt_id' => $this->rt->id,
                'id_wilayah' => $this->rt->id,
            ]);
    }
}
