<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\WilayahResource\Pages\CreateWilayah;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_petugas_rw_bisa_buat_kk_dan_kelola_rt_di_bawahnya_dan_lihat_list_rt(): void
    {
        $w = $this->seedDasar();
        $rw1 = Wilayah::where('level', 'rw')->first();

        // Buat RW kedua untuk menguji isolasi antar-RW
        $rw2 = Wilayah::create(['kode' => 'T.1.2', 'nama' => 'RW 002', 'level' => 'rw', 'parent_id' => $w['desa']->id]);
        $rt3 = Wilayah::create(['kode' => 'T.1.2.1', 'nama' => 'RT 003', 'level' => 'rt', 'parent_id' => $rw2->id]);

        $petugasRw1 = $this->makeUser('petugas-rw1', 'admin_rw', $rw1->id);
        $admin = $this->makeUser('admin', 'admin', null);

        // Buat KK di RT 001 (RW 001) dan RT 003 (RW 002)
        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [['nik' => '1111111111111111', 'hubungan' => 1]], $admin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [['nik' => '2222222222222222', 'hubungan' => 1]], $admin->id);
        $this->makeKeluarga($rt3->id, '3333333333333333', [['nik' => '3333333333333333', 'hubungan' => 1]], $admin->id);

        $this->actingAs($petugasRw1);

        // RW1 hanya melihat 2 KK (di RT 001 & RT 002 miliknya), KK di RT 003 tidak terlihat
        $this->assertSame(2, Keluarga::count());

        // Petugas RW bisa melakukan tugas petugas RT: membuat KK di RT bawahnya
        $this->assertTrue($petugasRw1->can('create', Keluarga::class));
        $kkRw = $this->makeKeluarga($w['rt1']->id, '4444444444444444', [['nik' => '4444444444444444', 'hubungan' => 1]], $petugasRw1->id);
        $this->assertSame('4444444444444444', $kkRw->fresh()->no_kk);

        // RW1 hanya melihat RT di bawah RW miliknya, tidak boleh melihat RT atau RW lain
        $response = $this->get('/admin/wilayah');
        $response->assertOk();
        $response->assertSee('RT 001');
        $response->assertSee('RT 002');
        $response->assertDontSee('RT 003'); // RT di bawah RW 002 tidak boleh terlihat
        $response->assertDontSee('RW 002');

        // Mode flat juga menghormati batasan subtree RW miliknya
        $responseFlat = $this->get('/admin/wilayah?mode=semua');
        $responseFlat->assertOk();
        $responseFlat->assertSee('RW 001');
        $responseFlat->assertSee('RT 001');
        $responseFlat->assertSee('RT 002');
        $responseFlat->assertDontSee('RT 003');
        $responseFlat->assertDontSee('RW 002');

        // RW1 bisa membuat RT baru
        Livewire::test(CreateWilayah::class)
            ->fillForm([
                'kode' => 'T.1.1.1.1.1.3',
                'nama' => 'RT 003 RW 001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wilayah', [
            'kode' => 'T.1.1.1.1.1.3',
            'nama' => 'RT 003 RW 001',
            'level' => 'rt',
            'parent_id' => $rw1->id,
        ]);

        // RW1 tidak boleh mengakses manajemen pengguna
        $this->get('/admin/pengguna')->assertForbidden();
    }

    public function test_petugas_rt_hanya_melihat_rt_dia_dan_tidak_bisa_akses_wilayah_atau_user(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);
        $rt1User = $this->makeUser('rt1', 'admin_rt', $w['rt1']->id);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [['nik' => '1111111111111111', 'hubungan' => 1]], $admin->id);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [['nik' => '2222222222222222', 'hubungan' => 1]], $admin->id);

        $this->actingAs($rt1User);

        // Hanya melihat KK di RT dia saja (1 KK)
        $this->assertSame(1, Keluarga::count());

        // Boleh menambah KK di RT dia
        $this->assertTrue($rt1User->can('create', Keluarga::class));

        // Tidak boleh membuka menu wilayah atau pengguna
        $this->get('/admin/wilayah')->assertForbidden();
        $this->get('/admin/pengguna')->assertForbidden();
    }

    public function test_superadmin_bisa_mengakses_semua_dan_membuat_akun_admin_dan_petugas(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);

        $this->actingAs($superadmin);

        // Akses menu utama
        $this->get('/admin/wilayah')->assertOk();
        $this->get('/admin/pengguna')->assertOk();
        $this->get('/admin/pengguna/create')->assertOk();
        $this->get('/admin/wilayah/create')->assertOk();

        // Bisa melihat seluruh RW dan RT
        $this->assertSame(Wilayah::count(), Wilayah::whereIn('level', ['negara', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'rw', 'rt'])->count());

        // Bisa membuat KK
        $this->assertTrue($superadmin->can('create', Keluarga::class));

        // Superadmin bisa membuat akun Petugas RW
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'petugas-rw-baru',
                'name' => 'Petugas RW Baru',
                'password' => 'password',
                'role' => 'admin_rw',
                'id_wilayah' => $w['rw']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Superadmin bisa membuat akun Petugas RT
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'petugas-rt-baru',
                'name' => 'Petugas RT Baru',
                'password' => 'password',
                'role' => 'admin_rt',
                'id_wilayah' => $w['rt1']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Superadmin bisa membuat akun Admin
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'admin-baru',
                'name' => 'Admin Baru',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['username' => 'admin-baru']);
    }

    public function test_role_admin_bisa_semua_tugas_superadmin_kecuali_tidak_bisa_membuat_admin(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);

        $this->actingAs($admin);

        // Bisa mengakses menu wilayah dan pengguna
        $this->get('/admin/wilayah')->assertOk();
        $this->get('/admin/pengguna')->assertOk();
        $this->get('/admin/wilayah/create')->assertOk();
        $this->get('/admin/pengguna/create')->assertOk();

        // Bisa membuat KK
        $this->assertTrue($admin->can('create', Keluarga::class));
        $kkAdmin = $this->makeKeluarga($w['rt1']->id, '5555555555555555', [['nik' => '5555555555555555', 'hubungan' => 1]], $admin->id);
        $this->assertSame('5555555555555555', $kkAdmin->fresh()->no_kk);

        // Bisa membuat/mendaftarkan wilayah baru
        Livewire::test(CreateWilayah::class)
            ->fillForm([
                'level' => 'rw',
                'parent_id' => $w['desa']->id,
                'kode' => 'T.1.1.1.1.2',
                'nama' => 'RW 002',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wilayah', ['kode' => 'T.1.1.1.1.2']);

        // Bisa membuat akun Petugas RW dan RT
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rw-oleh-admin',
                'name' => 'Petugas RW dari Admin',
                'password' => 'password',
                'role' => 'admin_rw',
                'id_wilayah' => $w['rw']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['username' => 'rw-oleh-admin']);

        // KECUALI: Admin TIDAK DAPAT membuat akun Admin lagi
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'admin-kedua',
                'name' => 'Admin Kedua',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertDatabaseMissing('users', ['username' => 'admin-kedua']);

        // KECUALI: Admin juga tidak dapat membuat akun Superadmin
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'sa-oleh-admin',
                'name' => 'SA oleh Admin',
                'password' => 'password',
                'role' => 'superadmin',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertDatabaseMissing('users', ['username' => 'sa-oleh-admin']);
    }

    public function test_admin_tidak_dapat_menonaktifkan_admin_lain_dan_superadmin(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $admin1 = $this->makeUser('admin1', 'admin', null);
        $admin2 = $this->makeUser('admin2', 'admin', null);
        $petugasRw = $this->makeUser('rw1', 'admin_rw', $w['rw']->id);
        $petugasRt = $this->makeUser('rt1', 'admin_rt', $w['rt1']->id);

        // 1. Admin tidak boleh menonaktifkan dirinya sendiri
        $this->assertFalse($admin1->can('deactivate', $admin1));
        // 2. Admin tidak boleh menonaktifkan admin lain
        $this->assertFalse($admin1->can('deactivate', $admin2));
        // 3. Admin tidak boleh menonaktifkan superadmin
        $this->assertFalse($admin1->can('deactivate', $superadmin));
        // 4. Admin boleh menonaktifkan petugas RW dan RT
        $this->assertTrue($admin1->can('deactivate', $petugasRw));
        $this->assertTrue($admin1->can('deactivate', $petugasRt));

        // 5. Superadmin tidak boleh menonaktifkan superadmin
        $this->assertFalse($superadmin->can('deactivate', $superadmin));
        // 6. Superadmin boleh menonaktifkan admin
        $this->assertTrue($superadmin->can('deactivate', $admin1));
        $this->assertTrue($superadmin->can('deactivate', $admin2));

        // Uji aksi UI pada tabel pengguna saat login sebagai admin1:
        $this->actingAs($admin1);

        Livewire::test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertTableActionHidden('toggleAktif', $admin2->getKey())
            ->assertTableActionHidden('toggleAktif', $superadmin->getKey())
            ->assertTableActionHidden('toggleAktif', $admin1->getKey())
            ->assertTableActionVisible('toggleAktif', $petugasRw->getKey())
            ->assertTableActionVisible('toggleAktif', $petugasRt->getKey())
            ->callTableAction('toggleAktif', $petugasRw->getKey());

        $this->assertFalse($petugasRw->fresh()->is_active);

        // Pada halaman edit admin2: form is_active dinonaktifkan (disabled) untuk admin lain
        Livewire::test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $admin2->getKey()])
            ->assertFormFieldIsDisabled('is_active')
            ->fillForm(['name' => 'Admin Dua Diupdate'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($admin2->fresh()->is_active);
        $this->assertSame('Admin Dua Diupdate', $admin2->fresh()->name);

        // Uji saat login sebagai superadmin: bisa menonaktifkan admin
        $this->actingAs($superadmin);

        Livewire::test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertTableActionVisible('toggleAktif', $admin1->getKey())
            ->callTableAction('toggleAktif', $admin1->getKey());

        $this->assertFalse($admin1->fresh()->is_active);
    }
}
