<?php

namespace Tests\Feature;

use App\Filament\Pages\KonfirmasiPengajuan;
use App\Filament\Pages\PengaturanDesa;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\KeluargaResource\Pages\ViewKeluarga;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\ValidasiPerubahanResource;
use App\Models\ValidasiPerubahan;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValidasiDanDesaTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_alur_validasi_berjenjang_rt_ke_rw_ke_desa_dan_rollback_saat_ditolak(): void
    {
        $w = $this->seedDasar();
        $adminDesa = $this->makeUser('kades', 'admin_desa', $w['desa']->id);
        $adminRw = $this->makeUser('ketua-rw', 'admin_rw', $w['rw']->id);
        $adminRt = $this->makeUser('ketua-rt1', 'admin_rt', $w['rt1']->id);

        // 1. Admin RT menambahkan keluarga baru -> otomatis membuat tiket validasi 'menunggu_rw'
        $this->actingAs($adminRt);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
            ['nik' => '6371010101850002', 'hubungan' => 2],
        ], $adminRt->id);

        $tiketTambah = ValidasiPerubahan::where('nama_tabel', 'keluarga')
            ->where('id_record', $keluarga->id)
            ->where('aksi', 'created')
            ->first();

        $this->assertNotNull($tiketTambah);
        $this->assertSame(ValidasiPerubahan::STATUS_MENUNGGU_RW, $tiketTambah->status);

        // Chain of command: Kepala Desa TIDAK boleh melompati Admin RW saat status masih menunggu_rw
        $this->assertFalse($tiketTambah->bolehDiverifikasiDesaOleh($adminDesa));
        $this->assertFalse($tiketTambah->bolehDitolakOleh($adminDesa));
        try {
            $tiketTambah->setujuiOlehDesa($adminDesa, 'Coba bypass RW');
            $this->fail('Kepala Desa seharusnya tidak boleh melompati verifikasi RW.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Admin RW', $e->getMessage());
        }

        // 2. Admin RW melihat tiket di antreannya dan menyetujui (meneruskan ke Desa)
        $this->actingAs($adminRw);
        $this->assertTrue($tiketTambah->bolehDiverifikasiRwOleh($adminRw));
        $tiketTambah->setujuiOlehRw($adminRw, 'Data KK baru sudah diperiksa RW');

        $this->assertSame(ValidasiPerubahan::STATUS_MENUNGGU_DESA, $tiketTambah->fresh()->status);
        $this->assertSame($adminRw->id, $tiketTambah->fresh()->id_verifikator_rw);

        // 3. Admin Desa / Kepala Desa menyetujui tahap akhir
        $this->actingAs($adminDesa);
        $this->assertTrue($tiketTambah->fresh()->bolehDiverifikasiDesaOleh($adminDesa));
        $tiketTambah->fresh()->setujuiOlehDesa($adminDesa, 'Disahkan oleh Pemerintah Desa');

        $this->assertSame(ValidasiPerubahan::STATUS_DISETUJUI, $tiketTambah->fresh()->status);
        $this->assertSame($adminDesa->id, $tiketTambah->fresh()->id_verifikator_desa);

        // 4. Admin RT mengedit alamat KK -> perubahan DITAHAN (deferred) sampai disahkan Kepala Desa
        $this->actingAs($adminRt);
        $alamatLama = $keluarga->fresh()->alamat;
        $keluarga->fresh()->update(['alamat' => 'Jl. Pengajuan Baru No. 99']);

        // Belum disetujui RW/Desa: data resmi di database HARUS tetap alamat lama
        $this->assertSame($alamatLama, $keluarga->fresh()->alamat);

        $tiketEdit = ValidasiPerubahan::where('nama_tabel', 'keluarga')
            ->where('id_record', $keluarga->id)
            ->where('aksi', 'updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($tiketEdit);
        $this->assertSame(ValidasiPerubahan::STATUS_MENUNGGU_RW, $tiketEdit->status);

        // Halaman Konfirmasi Pengajuan menampilkan status menunggu verifikasi, pintasan, dan rincian perubahan
        $this->get(KonfirmasiPengajuan::urlForTickets([$tiketEdit->id]))
            ->assertOk()
            ->assertSee('Perubahan Anda Berhasil Diajukan')
            ->assertSee('Buka Halaman Validasi')
            ->assertSee('Kembali ke Dashboard')
            ->assertSee('Jl. Pengajuan Baru No. 99');

        // Setelah disetujui RW (menunggu Desa), data resmi MASIH alamat lama
        $this->actingAs($adminRw);
        $tiketEdit->setujuiOlehRw($adminRw, 'Alamat baru sudah dicek RW');
        $this->assertSame(ValidasiPerubahan::STATUS_MENUNGGU_DESA, $tiketEdit->fresh()->status);
        $this->assertSame($alamatLama, $keluarga->fresh()->alamat);

        // Baru setelah Kepala Desa mengesahkan, data resmi berubah ke alamat baru (dan terdekripsi bersih)
        $this->actingAs($adminDesa);
        $tiketEdit->fresh()->setujuiOlehDesa($adminDesa, 'Disahkan Kepala Desa');
        $this->assertSame(ValidasiPerubahan::STATUS_DISETUJUI, $tiketEdit->fresh()->status);
        $this->assertSame('Jl. Pengajuan Baru No. 99', $keluarga->fresh()->alamat);

        // 5. Admin RT mengajukan pindah lewat tombol Ajukan Pindah di ViewKeluarga
        $this->actingAs($adminRt);
        $anggotaPindah = $keluarga->anggota()->orderBy('id')->first();

        Livewire::test(ViewKeluarga::class, ['record' => $keluarga->id])
            ->callAction('ajukan_pindah', [
                'jenis_mutasi' => 'anggota',
                'id_anggota' => $anggotaPindah->id,
                'tipe_tujuan' => 'keluar_desa',
                'alamat_tujuan' => 'Jl. Baru No. 88, Kota Sebelah',
                'catatan_pengaju' => 'Pindah mengikuti pekerjaan',
            ])
            ->assertHasNoActionErrors();

        // Status kependudukan BELUM berubah sebelum disahkan Kepala Desa
        $this->assertSame('menetap', $anggotaPindah->fresh()->status_kependudukan);

        $tiketPindah = ValidasiPerubahan::where('aksi', 'pindah')
            ->where('nama_tabel', 'anggota')
            ->where('id_record', $anggotaPindah->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($tiketPindah);
        $this->assertSame(ValidasiPerubahan::STATUS_MENUNGGU_RW, $tiketPindah->status);

        // 6. Jika Admin RW menolak pengajuan pindah tersebut, status anggota tetap 'menetap'
        $this->actingAs($adminRw);
        $tiketPindah->tolak($adminRw, 'Surat pengantar belum lengkap');

        $this->assertSame(ValidasiPerubahan::STATUS_DITOLAK, $tiketPindah->fresh()->status);
        $this->assertSame('menetap', $anggotaPindah->fresh()->status_kependudukan);

        // 7. Jika diajukan ulang lalu disetujui RW dan Kepala Desa, barulah status resmi menjadi 'pindah'
        $this->actingAs($adminRt);
        Livewire::test(ViewKeluarga::class, ['record' => $keluarga->id])
            ->callAction('ajukan_pindah', [
                'jenis_mutasi' => 'anggota',
                'id_anggota' => $anggotaPindah->id,
                'tipe_tujuan' => 'keluar_desa',
                'alamat_tujuan' => 'Jl. Baru No. 88, Kota Sebelah',
                'catatan_pengaju' => 'Surat pengantar sudah lengkap',
            ])
            ->assertHasNoActionErrors();

        $tiketPindah2 = ValidasiPerubahan::where('aksi', 'pindah')
            ->where('nama_tabel', 'anggota')
            ->where('id_record', $anggotaPindah->id)
            ->latest('id')
            ->first();

        $this->actingAs($adminRw);
        $tiketPindah2->setujuiOlehRw($adminRw, 'Lengkap');
        $this->assertSame('menetap', $anggotaPindah->fresh()->status_kependudukan);

        $this->actingAs($adminDesa);
        $tiketPindah2->fresh()->setujuiOlehDesa($adminDesa, 'Disahkan pindah');
        $this->assertSame('pindah', $anggotaPindah->fresh()->status_kependudukan);
    }

    public function test_admin_desa_dapat_kelola_admin_rw_rt_lihat_log_dan_atur_kustomisasi_desa(): void
    {
        $w = $this->seedDasar();
        $desaLain = Wilayah::create([
            'kode' => 'T.1.1.1.2',
            'nama' => 'Desa Tetangga',
            'level' => 'desa',
            'tipe' => 'desa',
            'parent_id' => $w['kecamatan']->id,
        ]);
        $rwLain = Wilayah::create([
            'kode' => 'T.1.1.1.2.1',
            'nama' => 'RW 001 Tetangga',
            'level' => 'rw',
            'parent_id' => $desaLain->id,
        ]);

        $adminDesa = $this->makeUser('admin-desa-uji', 'admin_desa', $w['desa']->id);
        $rwUser = $this->makeUser('rw-desa-uji', 'admin_rw', $w['rw']->id);
        $rtUser = $this->makeUser('rt-desa-uji', 'admin_rt', $w['rt1']->id);
        $rwTetanggaUser = $this->makeUser('rw-tetangga', 'admin_rw', $rwLain->id);

        $this->actingAs($adminDesa);

        // 1. Admin Desa dapat membuat akun Admin RT di desanya sendiri
        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => 'rt2-oleh-desa',
                'name' => 'Ketua RT 02',
                'password' => 'password',
                'role' => 'admin_rt',
                'id_wilayah' => $w['rt2']->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'username' => 'rt2-oleh-desa',
            'role' => 'admin_rt',
            'id_wilayah' => $w['rt2']->id,
        ]);

        // 2. Admin Desa TIDAK BOLEH membuat atau menonaktifkan akun di desa lain
        $this->assertTrue($adminDesa->can('deactivate', $rwUser));
        $this->assertTrue($adminDesa->can('deactivate', $rtUser));
        $this->assertFalse($adminDesa->can('deactivate', $rwTetanggaUser));

        // 3. Admin Desa dapat melihat Log Aktivitas bawahan di desanya
        $logsCount = AuditLogResource::getEloquentQuery()->count();
        $this->assertGreaterThan(0, $logsCount);

        // 4. Admin Desa dapat mengatur kustomisasi desa (nama tampilan, warna tema, kepala desa, & izin NIK RW)
        Livewire::test(PengaturanDesa::class)
            ->fillForm([
                'nama_tampilan' => 'Desa Maju Jaya',
                'slogan' => 'Melayani Warga dengan Sepenuh Hati',
                'warna_tema' => 'emerald',
                'nama_kepala_desa' => 'Bpk. Sutiyo Wibowo',
                'nip_kepala_desa' => '198001012005011001',
                'wajib_validasi_berjenjang' => true,
                'rw_lihat_penuh' => true,
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $desaSegar = $w['desa']->fresh();
        $this->assertSame('Desa Maju Jaya', $desaSegar->pengaturanDesa('nama_tampilan'));
        $this->assertSame('emerald', $desaSegar->pengaturanDesa('warna_tema'));
        $this->assertSame('Bpk. Sutiyo Wibowo', $desaSegar->pengaturanDesa('nama_kepala_desa'));
        $this->assertTrue((bool) $desaSegar->pengaturanDesa('rw_lihat_penuh'));

        // Karena rw_lihat_penuh diaktifkan oleh Desa, Admin RW di desa tersebut kini boleh melihat NIK/No KK penuh
        $this->assertTrue($rwUser->fresh()->bolehLihatDataPenuh());

        // Sidebar merender nama tampilan desa yang baru dikustomisasi
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Desa Maju Jaya')
            ->assertSee('Melayani Warga dengan Sepenuh Hati');

        // Halaman Validasi & Persetujuan dapat dibuka dengan baik
        $this->get(ValidasiPerubahanResource::getUrl('index'))
            ->assertOk();
    }

    public function test_arsip_snapshot_berkala_otomatis_sesuai_interval_jam_dan_ekspor_dataset_latih(): void
    {
        $w = $this->seedDasar();
        $adminDesa = $this->makeUser('kades-snapshot', 'admin_desa', $w['desa']->id);
        $adminRw = $this->makeUser('rw-snapshot', 'admin_rw', $w['rw']->id);

        // Buat 2 keluarga dengan total 3 warga di desa
        $kk1 = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
            ['nik' => '6371010101850002', 'hubungan' => 2],
        ], $adminDesa->id);
        $kk1->ekonomi()->create(['tahun_data' => 2026, 'kategori_ekonomi' => 'Rentan Miskin', 'created_by' => $adminDesa->id]);

        $kk2 = $this->makeKeluarga($w['rt2']->id, '6371010101000002', [
            ['nik' => '6371010101900003', 'hubungan' => 1],
        ], $adminDesa->id);
        $kk2->ekonomi()->create(['tahun_data' => 2026, 'kategori_ekonomi' => 'Mampu', 'created_by' => $adminDesa->id]);

        // Set interval arsip otomatis = 36 jam lewat PengaturanDesa
        $this->actingAs($adminDesa);
        Livewire::test(PengaturanDesa::class)
            ->fillForm([
                'nama_tampilan' => 'Desa Uji Snapshot',
                'slogan' => 'Dataset Historis Desa',
                'warna_tema' => 'teal',
                'wajib_validasi_berjenjang' => true,
                'rw_lihat_penuh' => false,
                'arsip_otomatis_aktif' => true,
                'interval_arsip_jam' => 36,
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        // 1. Snapshot baseline pertama otomatis terbuat dan merekam SEMUA warga (3 warga, 2 KK)
        $this->assertSame(1, \App\Models\ArsipSnapshotDesa::count());
        $snap1 = \App\Models\ArsipSnapshotDesa::first();
        $this->assertSame(2, $snap1->jumlah_keluarga);
        $this->assertSame(3, $snap1->jumlah_warga);
        $this->assertSame(36, $snap1->interval_jam);

        // 2. Jika baru lewat 10 jam (belum 36 jam), walaupun ada perubahan, belum membuat snapshot baru
        $this->travel(10)->hours();
        $kk1->aset()->create(['jenis_aset' => 'Sepeda Motor', 'keterangan' => 'Honda Beat', 'created_by' => $adminDesa->id]);
        \App\Models\ArsipSnapshotDesa::prosesSemuaDesaJatuhTempo();
        $this->assertSame(1, \App\Models\ArsipSnapshotDesa::count());

        // 3. Setelah lewat 37 jam sejak snapshot pertama, jadwal otomatis merekam Snapshot ke-2 berisi seluruh warga
        $this->travel(27)->hours();
        \App\Models\ArsipSnapshotDesa::prosesSemuaDesaJatuhTempo();
        $this->assertSame(2, \App\Models\ArsipSnapshotDesa::count());

        $snap2 = \App\Models\ArsipSnapshotDesa::latest('id')->first();
        $this->assertSame(2, $snap2->jumlah_keluarga);
        $this->assertSame(3, $snap2->jumlah_warga);
        $this->assertCount(3, $snap2->snapshotData['dataset_latih']);
        $this->assertNotEmpty($snap2->snapshotData['wilayah_list']);
        $this->assertNotEmpty($snap2->snapshotData['admin_list']);
        $this->assertArrayHasKey('nama_ibu', $snap2->snapshotData['keluarga_list'][0]['anggota'][0]);
        $this->assertArrayHasKey('nama_ayah', $snap2->snapshotData['keluarga_list'][0]['anggota'][0]);
        $this->assertArrayHasKey('tempat_lahir', $snap2->snapshotData['keluarga_list'][0]['anggota'][0]);

        // 4. Unduh CSV Dataset Latih mengandung kolom fitur ML dan label ekonomi tanpa membocorkan NIK asli
        $csvResponse = \App\Filament\Resources\ArsipSnapshotDesaResource::streamCsvDataset($snap2);
        ob_start();
        $csvResponse->sendContent();
        $csvContent = (string) ob_get_clean();

        $this->assertStringContainsString('label_kategori_ekonomi', $csvContent);
        $this->assertStringContainsString('Rentan Miskin', $csvContent);
        $this->assertStringContainsString('Mampu', $csvContent);
        $this->assertStringNotContainsString('6371010101800001', $csvContent);

        // 5. Hak akses: Kepala Desa bisa membuka halaman Arsip Snapshot, sedangkan Admin RW ditolak (403)
        $this->travelBack();
        $this->flushSession();
        $this->actingAs($adminDesa);
        $this->get(\App\Filament\Resources\ArsipSnapshotDesaResource::getUrl('index'))->assertOk();
        $this->flushSession();
        $this->actingAs($adminRw);
        $this->get(\App\Filament\Resources\ArsipSnapshotDesaResource::getUrl('index'))->assertForbidden();
    }
}

