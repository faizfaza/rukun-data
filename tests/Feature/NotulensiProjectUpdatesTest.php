<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogResource;
use App\Models\Anggota;
use App\Models\AsetKeluarga;
use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotulensiProjectUpdatesTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_dual_address_and_ktp_encryption(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        $k = Keluarga::create([
            'id_wilayah' => $w['rt1']->id,
            'no_kk' => '1234567890123456',
            'alamat' => 'Jl. Domisili No. 1',
            'alamat_ktp' => 'Jl. Asal KTP No. 99',
            'kode_pos' => '12345',
            'is_locked' => false,
        ]);

        $raw = \DB::table('keluarga')->where('id', $k->id)->first();
        // Database raw value must be encrypted
        $this->assertNotSame('Jl. Domisili No. 1', $raw->alamat);
        $this->assertNotSame('Jl. Asal KTP No. 99', $raw->alamat_ktp);

        // Accessor decrypts automatically
        $fresh = Keluarga::find($k->id);
        $this->assertSame('Jl. Domisili No. 1', $fresh->alamat);
        $this->assertSame('Jl. Asal KTP No. 99', $fresh->alamat_ktp);
    }

    public function test_locked_family_blocks_non_superadmin(): void
    {
        $w = $this->seedDasar();
        $rt = $this->makeUser('admin-rt', 'admin_rt', $w['rt1']->id);
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);

        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [
            ['nik' => '1234567890123456', 'nama' => 'Kepala', 'hub' => 1],
        ], $superadmin->id);

        $k->update(['is_locked' => true]);

        // RT user cannot update or delete locked family
        $this->assertFalse($rt->can('update', $k));
        $this->assertFalse($rt->can('delete', $k));

        // Superadmin CAN update and delete locked family
        $this->assertTrue($superadmin->can('update', $k));
        $this->assertTrue($superadmin->can('delete', $k));
    }

    public function test_status_kependudukan_defaults_to_menetap_and_supports_pindah(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [
            ['nik' => '1234567890123456', 'nama' => 'Warga Satu', 'hub' => 1],
        ], $admin->id);

        $anggota1 = $k->anggota()->first();
        $this->assertSame('menetap', $anggota1->status_kependudukan);

        // Update status to pindah
        $anggota1->update(['status_kependudukan' => 'pindah']);
        $this->assertSame('pindah', $anggota1->fresh()->status_kependudukan);
    }

    public function test_aset_keluarga_creation_and_relationship(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [
            ['nik' => '1234567890123456', 'nama' => 'Kepala', 'hub' => 1],
        ], $admin->id);

        $aset = $k->aset()->create([
            'jenis_aset' => 'Internet / WiFi',
            'keterangan' => 'Indihome 50 Mbps',
        ]);

        $this->assertDatabaseHas('aset_keluarga', [
            'id' => $aset->id,
            'id_keluarga' => $k->id,
            'jenis_aset' => 'Internet / WiFi',
            'created_by' => $admin->id,
        ]);

        $this->assertCount(1, $k->fresh()->aset);
        $this->assertSame('Internet / WiFi', $k->fresh()->aset->first()->jenis_aset);
    }

    public function test_audit_log_scoping_by_role(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $rw = $this->makeUser('admin-rw', 'admin_rw', $w['rw']->id);
        $rt1 = $this->makeUser('admin-rt1', 'admin_rt', $w['rt1']->id);
        $rt2 = $this->makeUser('admin-rt2', 'admin_rt', $w['rt2']->id);

        AuditLog::query()->delete();

        AuditLog::create(['id_pengguna' => $rt1->id, 'aksi' => 'created', 'nama_tabel' => 'keluarga', 'id_record' => 1]);
        AuditLog::create(['id_pengguna' => $rt2->id, 'aksi' => 'created', 'nama_tabel' => 'keluarga', 'id_record' => 2]);
        AuditLog::create(['id_pengguna' => $rw->id, 'aksi' => 'updated', 'nama_tabel' => 'wilayah', 'id_record' => $w['rw']->id]);
        AuditLog::create(['id_pengguna' => $superadmin->id, 'aksi' => 'created', 'nama_tabel' => 'users', 'id_record' => 99]);

        // RT1 sees only own logs (1)
        $this->actingAs($rt1);
        $this->assertSame(1, AuditLogResource::getEloquentQuery()->count());

        // RW sees own logs + RT1 + RT2 subordinate logs (3)
        $this->actingAs($rw);
        $this->assertSame(3, AuditLogResource::getEloquentQuery()->count());

        // Superadmin sees all logs (4)
        $this->actingAs($superadmin);
        $this->assertSame(4, AuditLogResource::getEloquentQuery()->count());
    }

    public function test_cetak_keluarga_and_rekap_routes(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        $k = $this->makeKeluarga($w['rt1']->id, '1234567890123456', [
            ['nik' => '1234567890123456', 'nama' => 'Kepala Keluarga', 'hub' => 1],
        ], $admin->id);

        // Cetak KK
        $resKk = $this->get(route('keluarga.cetak', $k));
        $resKk->assertOk();
        $resKk->assertSee('KARTU KELUARGA');
        $resKk->assertSee('Kepala Keluarga');

        // Cetak Rekap RT
        $resRekapRt = $this->get(route('wilayah.cetak-warga', $w['rt1']));
        $resRekapRt->assertOk();
        $resRekapRt->assertSee('REKAPITULASI DATA PENDUDUK');

        // Cetak Rekap RW
        $resRekapRw = $this->get(route('wilayah.cetak-warga', $w['rw']));
        $resRekapRw->assertOk();
        $resRekapRw->assertSee('REKAPITULASI DATA PENDUDUK');
    }
}
