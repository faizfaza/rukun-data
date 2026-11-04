<?php

namespace Tests\Feature;

use App\Filament\Resources\AnggotaResource\Pages\EditAnggota;
use App\Filament\Resources\KeluargaResource\Pages\CreateKeluarga;
use App\Filament\Resources\KeluargaResource\Pages\EditKeluarga;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalProfilesTest extends TestCase
{
    use FixturesRukunData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sensitif.hmac_key' => str_repeat('t', 32)]);
    }

    public function test_ancestor_activation_preserves_local_flags_and_blocks_new_assignments(): void
    {
        $w = $this->seedDasar();
        $w['rw']->update(['is_active' => false]);
        $this->assertFalse($w['rt1']->fresh()->aktifEfektif());
        $this->assertTrue($w['rt1']->fresh()->is_active);
        $this->assertFalse(Wilayah::operasional()->whereKey($w['rt1']->id)->exists());
        try {
            $this->makeUser('blocked', 'admin_rt', $w['rt1']->id);
            $this->fail('Inactive ancestor accepted');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('users', ['username' => 'blocked']);
        }
        $w['rt2']->update(['is_active' => false]);
        $w['rw']->update(['is_active' => true]);
        $this->assertTrue($w['rt1']->fresh()->aktifEfektif());
        $this->assertFalse($w['rt2']->fresh()->aktifEfektif());
    }

    public function test_optional_admin_identity_persists_encrypted_and_can_be_cleared_through_form(): void
    {
        $w = $this->seedDasar();
        $root = $this->makeUser('root', 'superadmin', null);
        $this->actingAs($root);
        Livewire::test(CreateUser::class)->fillForm(['username' => 'profil', 'name' => 'Profil', 'password' => 'password', 'role' => 'admin_rt', 'id_wilayah' => $w['rt1']->id, 'identitas' => ['kontak' => '+6281234']])->call('create')->assertHasNoFormErrors();
        $u = User::where('username', 'profil')->firstOrFail();
        $this->assertSame('+6281234', $u->identitas['kontak']);
        $this->assertStringNotContainsString('+6281234', DB::table('users')->where('id', $u->id)->value('identitas'));
        $this->assertArrayNotHasKey('identitas', $u->toArray());
        Livewire::test(EditUser::class, ['record' => $u->id])->assertFormSet(['identitas.kontak' => '+6281234'])->fillForm(['identitas' => ['kontak' => null]])->call('save')->assertHasNoFormErrors();
        $this->assertNull($u->fresh()->identitas['kontak']);
    }

    public function test_directory_subtree_excludes_global_accounts(): void
    {
        $w = $this->seedDasar();
        $root = $this->makeUser('root', 'superadmin', null);
        $rt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);
        $this->actingAs($root);
        Livewire::test(ListUsers::class)->set('wilayah', $w['provinsi']->id)->assertCanSeeTableRecords([$rt])->assertCanNotSeeTableRecords([$root]);
    }

    public function test_import_is_idempotent_dry_run_and_missing_parent_rolls_back(): void
    {
        $this->seedDasar();
        $file = tempnam(sys_get_temp_dir(), 'wilayah');
        $data = ['source_url' => 'https://example.test/snapshot', 'version' => 'test', 'license' => 'test', 'rows' => [['code' => '01', 'name' => 'Sumber uji', 'level' => 'provinsi', 'parent_code' => null]]];
        file_put_contents($file, json_encode($data));
        $this->artisan('wilayah:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseMissing('wilayah', ['kode_sumber' => '01']);
        $this->artisan('wilayah:import', ['file' => $file])->assertSuccessful();
        $this->artisan('wilayah:import', ['file' => $file])->assertSuccessful();
        $this->assertSame(1, Wilayah::where('kode_sumber', '01')->count());
        $this->assertFalse(Wilayah::where('kode_sumber', '01')->first()->is_active);
        $data['rows'][] = ['code' => '02.01', 'name' => 'Induk hilang', 'level' => 'kabupaten_kota', 'parent_code' => '02'];
        file_put_contents($file, json_encode($data));
        $this->artisan('wilayah:import', ['file' => $file])->assertFailed();
        $this->assertDatabaseMissing('wilayah', ['kode_sumber' => '02.01']);
        unlink($file);
    }

    public function test_new_migration_preserves_legacy_rows_and_activates_existing_regions(): void
    {
        $w = $this->seedDasar();
        $u = $this->makeUser('legacy', 'admin_rt', $w['rt1']->id);
        $migration = require database_path('migrations/2026_10_31_000001_add_operational_profiles.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($w['rt1']->fresh()->aktifEfektif());
        $this->assertSame($u->id, User::where('username', 'legacy')->first()->id);
        $this->assertNull($u->fresh()->identitas);
    }

    public function test_family_form_rolls_back_when_second_member_violates_unique_nik(): void
    {
        $w = $this->seedDasar();
        $rt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);
        $this->actingAs($rt);
        $row = ['nik' => '1234567890123456', 'nama_lengkap' => 'Nama Uji', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2000-01-01', 'jenis_kelamin' => 'L', 'status_kawin' => 'belum_kawin', 'id_agama' => 1, 'id_pendidikan' => 1, 'id_pekerjaan' => 1, 'id_hubungan' => 1];
        $other = $row;
        $other['id_hubungan'] = 3;
        try {
            Livewire::test(CreateKeluarga::class)
                ->fillForm(['id_wilayah' => $w['rt1']->id, 'no_kk' => '1234567890123456', 'alamat' => 'Uji', 'kode_pos' => '12345', 'anggota' => [$row, $other]])->call('create');
            $this->fail('Duplicate NIK accepted');
        } catch (QueryException) {
            $this->assertSame(0, Keluarga::count());
            $this->assertSame(0, Anggota::count());
        }
    }

    public function test_existing_member_profile_can_be_updated_in_inactive_region_but_new_assignment_cannot(): void
    {
        $w = $this->seedDasar();
        $rt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);
        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [['nik' => '1234567890123456', 'nama' => 'Uji', 'hub' => 1]], $rt->id);
        $w['rw']->update(['is_active' => false]);
        $member = $k->anggota()->firstOrFail();
        $member->update(['profil' => ['nama_sekolah' => 'Sekolah Uji']]);
        $this->assertSame('Sekolah Uji', $member->fresh()->profil['nama_sekolah']);
        $this->expectException(ValidationException::class);
        $this->makeUser('baru', 'admin_rt', $w['rt1']->id);
    }

    public function test_member_edit_hydrates_sensitive_fields_and_profile(): void
    {
        $w = $this->seedDasar();
        $rt = $this->makeUser('rt', 'admin_rt', $w['rt1']->id);
        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [['nik' => '1234567890123456', 'hubungan' => 1]], $rt->id);
        $member = $k->anggota()->firstOrFail();
        $member->update(['profil' => ['nama_sekolah' => 'Sekolah Lama']]);
        $this->actingAs($rt);
        Livewire::test(EditAnggota::class, ['record' => $member->id])
            ->assertFormSet(['nik' => '1234567890123456', 'profil.nama_sekolah' => 'Sekolah Lama'])
            ->fillForm(['profil.nama_sekolah' => 'Sekolah Baru'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Sekolah Baru', $member->fresh()->profil['nama_sekolah']);
        Livewire::test(EditKeluarga::class, ['record' => $k->id])
            ->assertFormSet(['no_kk' => '1234567890123456'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Sekolah Baru', $member->fresh()->profil['nama_sekolah']);
    }

    public function test_create_admin_cannot_change_context_region(): void
    {
        $w = $this->seedDasar();
        $root = $this->makeUser('root', 'superadmin', null);
        $this->actingAs($root);
        Livewire::withQueryParams(['id_wilayah' => $w['rt1']->id])->test(CreateUser::class)
            ->fillForm(['username' => 'wrong-context', 'name' => 'Uji', 'password' => 'password', 'role' => 'admin_rt', 'id_wilayah' => $w['rt2']->id])
            ->call('create')->assertHasFormErrors(['id_wilayah']);
        $this->assertDatabaseMissing('users', ['username' => 'wrong-context']);
    }
}
