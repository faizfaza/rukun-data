<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\WilayahResource;
use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

// Fase 2.10: matriks kewenangan, pembatasan akun, data tersamarkan, dan audit.
class RoleAksesTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    // RW kedua (di luar kewenangan admin_rw pertama) untuk uji lintas wilayah.
    private function buatRwLuar(array $w): array
    {
        $rwLuar = Wilayah::create(['kode' => 'T.1.1.1.2.1', 'nama' => 'RW Luar', 'level' => 'rw', 'parent_id' => $w['desa']->id]);
        $rtLuar = Wilayah::create(['kode' => 'T.1.1.1.2.1.1', 'nama' => 'RT Luar', 'level' => 'rt', 'parent_id' => $rwLuar->id]);

        return ['rw' => $rwLuar, 'rt' => $rtLuar];
    }

    public function test_admin_rw_tidak_bisa_input_edit_hapus(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $superadmin->id);

        // Bisa melihat, tetapi tidak boleh membuat/mengubah/menghapus.
        $this->assertTrue($adminRw->can('view', $keluarga));
        $this->assertFalse($adminRw->can('create', Keluarga::class));
        $this->assertFalse($adminRw->can('update', $keluarga));
        $this->assertFalse($adminRw->can('delete', $keluarga));
        $this->assertFalse($adminRw->can('forceDelete', $keluarga));

        // admin_rt di RT yang sama boleh input/edit.
        $adminRt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);
        $this->assertTrue($adminRt->can('create', Keluarga::class));
        $this->assertTrue($adminRt->can('update', $keluarga));
    }

    public function test_admin_rw_tidak_bisa_membuat_akun_di_luar_rw(): void
    {
        $w = $this->seedDasar();
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);
        $luar = $this->buatRwLuar($w);

        $this->actingAs($adminRw);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rt-luar',
                'name' => 'RT Luar',
                'password' => 'password',
                'role' => 'admin_rt',
                'id_wilayah' => $luar['rt']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['id_wilayah']);

        $this->assertDatabaseMissing('users', ['username' => 'rt-luar']);
    }

    public function test_admin_rw_tidak_bisa_membuat_role_admin_rw(): void
    {
        $w = $this->seedDasar();
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $this->actingAs($adminRw);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rw-baru',
                'name' => 'RW Baru',
                'password' => 'password',
                'role' => 'admin_rw',
                'id_wilayah' => $w['rw']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertDatabaseMissing('users', ['username' => 'rw-baru']);
    }

    public function test_wilayah_akun_harus_cocok_level(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);

        $this->actingAs($superadmin);

        // Peran admin_rt wajib berwilayah RT, bukan RW.
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rt-salah',
                'name' => 'RT Salah Level',
                'password' => 'password',
                'role' => 'admin_rt',
                'id_wilayah' => $w['rw']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['id_wilayah']);

        $this->assertDatabaseMissing('users', ['username' => 'rt-salah']);
    }

    public function test_superadmin_membuat_akun_admin_rt_berhasil(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);

        $this->actingAs($superadmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rt-baru',
                'name' => 'RT Baru',
                'password' => 'password',
                'role' => 'admin_rt',
                'id_wilayah' => $w['rt1']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $baru = User::where('username', 'rt-baru')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->isAdminRt());
        $this->assertSame($superadmin->id, $baru->created_by);
        $this->assertTrue(Hash::check('password', $baru->password));
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $w = $this->seedDasar();
        $user = $this->makeUser('nonaktif', 'admin_rt', $w['rt1']->id);
        $user->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['username' => 'nonaktif', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_admin_rw_melihat_no_kk_tersamarkan(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $superadmin->id);

        $this->actingAs($adminRw);

        $masked = SensitiveData::mask('6371010101000001');

        $this->get("/admin/keluarga/{$keluarga->id}")
            ->assertOk()
            ->assertSee($masked, false)
            ->assertDontSee('6371010101000001', false);
    }

    public function test_admin_rw_melihat_penuh_saat_config_diaktifkan(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $superadmin->id);

        config(['sensitif.rw_lihat_penuh' => true]);

        $this->actingAs($adminRw);

        $this->get("/admin/keluarga/{$keluarga->id}")
            ->assertOk()
            ->assertSee('6371010101000001', false);
    }

    public function test_lingkup_akun_superadmin_vs_admin_rw(): void
    {
        $w = $this->seedDasar();
        $luar = $this->buatRwLuar($w);

        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);
        $this->makeUser('rt1', 'admin_rt', $w['rt1']->id);
        $this->makeUser('rt2', 'admin_rt', $w['rt2']->id);
        $this->makeUser('rt-luar', 'admin_rt', $luar['rt']->id);

        $this->actingAs($superadmin);
        $this->assertSame(5, UserResource::getEloquentQuery()->count());

        $this->actingAs($adminRw);
        $this->assertSame(2, UserResource::getEloquentQuery()->count());
    }

    public function test_menu_wilayah_bukan_untuk_admin_rw(): void
    {
        $w = $this->seedDasar();
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $this->actingAs($adminRw);
        $this->assertFalse(WilayahResource::canAccess());
    }

    public function test_audit_akun_tidak_menyimpan_password(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($superadmin);

        $this->makeUser('rahasia', 'admin_rt', $w['rt1']->id);

        $this->assertGreaterThan(0, AuditLog::where('nama_tabel', 'users')->count());

        foreach (AuditLog::where('nama_tabel', 'users')->get() as $log) {
            $serial = json_encode($log->getAttributes());
            $this->assertStringNotContainsString('password', $serial);
            $this->assertStringNotContainsString('rahasia', $serial);
        }
    }
}
