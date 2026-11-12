<?php

namespace Tests\Feature;

use App\Filament\Resources\KeluargaResource\Pages\EditKeluarga;
use App\Models\AuditLog;
use App\Support\AuditFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditDetailTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_hapus_satu_anggota_hanya_mencatat_satu_aksi(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
            ['nik' => '6371010101850002', 'hubungan' => 2],
            ['nik' => '6371010101850003', 'hubungan' => 3],
        ], $admin->id);

        $this->actingAs($admin);
        AuditLog::query()->delete();

        $component = Livewire::test(EditKeluarga::class, ['record' => $keluarga->id]);
        $anggota = $component->get('data.anggota');
        array_pop($anggota);

        $component->set('data.anggota', $anggota)->call('save')->assertHasNoFormErrors();

        $this->assertSame(1, AuditLog::where('aksi', 'deleted')->where('nama_tabel', 'anggota')->count());
        $this->assertSame(0, AuditLog::where('aksi', 'updated')->count(), 'Anggota/KK yang tidak berubah tidak boleh tercatat sebagai Ubah.');
    }

    public function test_perubahan_status_mencatat_versi_sebelum_dan_sesudah(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $admin->id);

        $this->actingAs($admin);
        AuditLog::query()->delete();

        $keluarga->anggota()->first()->update(['status_kependudukan' => 'pindah']);

        $log = AuditLog::where('aksi', 'updated')->where('nama_tabel', 'anggota')->sole();
        $info = AuditFormatter::describe($log);

        $this->assertSame('Menetap (Aktif)', $info['sebelum']['Status Kependudukan']);
        $this->assertSame('Pindah', $info['sesudah']['Status Kependudukan']);
        $this->assertStringContainsString("diubah dari 'Menetap (Aktif)' ke 'Pindah'", $info['ringkasan']);
        $this->assertCount(1, $info['perubahan']);
    }

    public function test_keluarga_memiliki_riwayat_aktivitas_tersendiri(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $admin->id);

        $this->actingAs($admin);

        // Buat perubahan pada anggota keluarga
        $anggota = $keluarga->anggota()->first();
        $anggota->update(['status_kependudukan' => 'pindah']);

        // Tambah aset
        $keluarga->aset()->create(['jenis_aset' => 'Sepeda Motor', 'keterangan' => 'Vario']);

        $logs = $keluarga->fresh()->riwayatAktivitas()->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertTrue($logs->contains('nama_tabel', 'anggota'));
        $this->assertTrue($logs->contains('nama_tabel', 'aset_keluarga'));

        // Cek halaman ViewKeluarga merender bagian Riwayat & Log Aktivitas
        $this->get('/admin/keluarga/'.$keluarga->id)
            ->assertOk()
            ->assertSee('Riwayat & Log Aktivitas Keluarga')
            ->assertSee('Sepeda Motor');
    }

    public function test_admin_rw_dapat_melihat_perubahan_oleh_superadmin_di_wilayahnya(): void
    {
        $w = $this->seedDasar();
        $superadmin = $this->makeUser('superadmin', 'superadmin', null);
        $rwUser = $this->makeUser('admin_rw', 'admin_rw', $w['rw']->id);
        $rt1User = $this->makeUser('admin_rt1', 'admin_rt', $w['rt1']->id);
        $rt2User = $this->makeUser('admin_rt2', 'admin_rt', $w['rt2']->id);

        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $superadmin->id);

        $aset = $keluarga->aset()->create([
            'jenis_aset' => 'Mobil',
            'keterangan' => 'Mempunyai Mobil 2',
            'created_by' => $superadmin->id,
        ]);

        // Superadmin mengubah keterangan aset di RT 01
        $this->actingAs($superadmin);
        $aset->update(['keterangan' => 'Mempunyai Mobil 1']);

        // Log perubahan dibuat oleh superadmin pada aset_keluarga
        $logAset = AuditLog::where('aksi', 'updated')
            ->where('nama_tabel', 'aset_keluarga')
            ->where('id_record', $aset->id)
            ->first();
        $this->assertNotNull($logAset);
        $this->assertSame($superadmin->id, $logAset->id_pengguna);

        // 1. Admin RW 01 HARUS DAPAT MELIHAT log perubahan aset oleh superadmin ini
        $this->actingAs($rwUser);
        $rwLogs = \App\Filament\Resources\AuditLogResource::getEloquentQuery()->get();
        $this->assertTrue($rwLogs->contains('id', $logAset->id), 'Admin RW harus dapat melihat log perubahan aset di wilayahnya oleh superadmin.');

        // Cek juga halaman ListAuditLogs dirender dengan benar untuk Admin RW
        \Livewire\Livewire::test(\App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs::class)
            ->assertCanSeeTableRecords([$logAset])
            ->assertSee('Mobil');

        // 2. Admin RT 01 juga dapat melihat perubahan di RT-nya
        $this->actingAs($rt1User);
        $rt1Logs = \App\Filament\Resources\AuditLogResource::getEloquentQuery()->get();
        $this->assertTrue($rt1Logs->contains('id', $logAset->id), 'Admin RT 01 harus dapat melihat log perubahan aset di RT-nya.');

        // 3. Admin RT 02 TIDAK DAPAT melihat perubahan yang terjadi di RT 01
        $this->actingAs($rt2User);
        $rt2Logs = \App\Filament\Resources\AuditLogResource::getEloquentQuery()->get();
        $this->assertFalse($rt2Logs->contains('id', $logAset->id), 'Admin RT 02 tidak boleh melihat log perubahan di RT 01.');
    }
}

