<?php

use App\Models\ArsipSnapshotDesa;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('arsip:snapshot-otomatis', function () {
    $jumlah = ArsipSnapshotDesa::prosesSemuaDesaJatuhTempo();
    $this->info("Snapshot arsip berkala selesai diproses ({$jumlah} desa direkam).");
})->purpose('Memproses snapshot arsip berkala seluruh warga desa secara otomatis sesuai interval jam tiap desa');

Artisan::command('simulasi:lengkong', function () {
    // Bersihkan jika sempat terbuat di Lengkong luar Bojongsoang (id=6726)
    ArsipSnapshotDesa::where('id_wilayah_desa', 6726)->delete();
    \App\Models\Keluarga::withoutGlobalScopes()->withTrashed()
        ->where('no_kk_hash', \App\Support\SensitiveData::hash('3204081811260009'))
        ->forceDelete();
    \App\Models\Wilayah::withoutGlobalScopes()->where('path', 'like', '%/6726/%')->where('level', 'rt')->delete();
    \App\Models\Wilayah::withoutGlobalScopes()->where('path', 'like', '%/6726/%')->where('level', 'rw')->delete();
    \App\Models\Wilayah::withoutGlobalScopes()->whereKey(6726)->update(['pengaturan' => null]);

    $desaLengkong = \App\Models\Wilayah::where('level', 'desa')
        ->where('nama', 'Lengkong')
        ->where('kode', 'like', '32.04.08%')
        ->first()
        ?? \App\Models\Wilayah::where('level', 'desa')->where('nama', 'Lengkong')->orderByDesc('id')->first();

    if (! $desaLengkong) {
        $this->error('Desa Lengkong tidak ditemukan.');

        return;
    }

    // 1. Buat / pastikan akun Kepala Desa Lengkong
    $kades = \App\Models\User::updateOrCreate(
        ['username' => 'kades_lengkong'],
        [
            'name' => 'H. Hendra Gunawan (Kades Lengkong)',
            'email' => 'kades_lengkong@rukundata.local',
            'password' => 'password',
            'role' => \App\Enums\Role::AdminDesa,
            'id_wilayah' => $desaLengkong->id,
            'is_active' => true,
            'identitas' => [
                'nama_lengkap' => 'H. Hendra Gunawan, S.IP.',
                'nik' => '3204081505780001',
                'kontak' => '081223344556',
                'jabatan' => 'Kepala Desa Lengkong',
            ],
        ]
    );

    auth()->guard('web')->setUser($kades);

    // 2. Pastikan ada RW 01 & RT 01, RT 02 di Desa Lengkong
    $rw01 = \App\Models\Wilayah::firstOrCreate(
        ['parent_id' => $desaLengkong->id, 'level' => 'rw', 'nama' => 'RW 01'],
        ['kode' => $desaLengkong->kode.'.RW01', 'is_active' => true]
    );
    $rt01 = \App\Models\Wilayah::firstOrCreate(
        ['parent_id' => $rw01->id, 'level' => 'rt', 'nama' => 'RT 01'],
        ['kode' => $rw01->kode.'.RT01', 'is_active' => true]
    );
    $rt02 = \App\Models\Wilayah::firstOrCreate(
        ['parent_id' => $rw01->id, 'level' => 'rt', 'nama' => 'RT 02'],
        ['kode' => $rw01->kode.'.RT02', 'is_active' => true]
    );

    // 3. Buat Keluarga Darmawan Putra di RT 02 Lengkong (kondisi awal sebelum diubah)
    $kk = \App\Models\Keluarga::withoutGlobalScopes()
        ->where('id_wilayah', $rt02->id)
        ->first();

    if (! $kk) {
        $kk = \App\Models\Keluarga::create([
            'id_wilayah' => $rt02->id,
            'no_kk' => '3204081811260009',
            'alamat' => 'Jl. Raya Lengkong No. 45',
            'alamat_ktp' => 'Jl. Raya Lengkong No. 45',
            'kode_pos' => '40287',
            'created_by' => $kades->id,
        ]);

        $kk->ekonomi()->create([
            'tahun_data' => 2026,
            'kategori_ekonomi' => 'Rentan Miskin',
            'created_by' => $kades->id,
        ]);

        $kk->aset()->create([
            'jenis_aset' => 'Sepeda Motor',
            'keterangan' => '1 Unit Honda Beat 2019',
            'created_by' => $kades->id,
        ]);

        \App\Models\Anggota::create([
            'id_keluarga' => $kk->id,
            'nik' => '3204081001800009',
            'nama_lengkap' => 'Darmawan Putra',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1980-01-10',
            'jenis_kelamin' => 'L',
            'status_kawin' => 'kawin',
            'status_kependudukan' => 'menetap',
            'id_agama' => 1,
            'id_pendidikan' => 4,
            'id_pekerjaan' => 3,
            'id_hubungan' => 1,
            'nama_ayah' => 'Suparman',
            'nama_ibu' => 'Siti Aminah',
            'penghasilan_bulanan' => 2500000,
            'created_by' => $kades->id,
        ]);

        \App\Models\Anggota::create([
            'id_keluarga' => $kk->id,
            'nik' => '3204082005840010',
            'nama_lengkap' => 'Ratna Ningsih',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1984-05-20',
            'jenis_kelamin' => 'P',
            'status_kawin' => 'kawin',
            'status_kependudukan' => 'menetap',
            'id_agama' => 1,
            'id_pendidikan' => 4,
            'id_pekerjaan' => 4,
            'id_hubungan' => 2,
            'nama_ayah' => 'Karnaen',
            'nama_ibu' => 'Wati',
            'penghasilan_bulanan' => 1500000,
            'created_by' => $kades->id,
        ]);
    }

    // Bersihkan snapshot lama agar terbentuk 2 Batch Global (BATCH-001 Sebelum Ubah & BATCH-002 Sesudah Ubah)
    ArsipSnapshotDesa::query()->delete();

    $desaLengkong->update([
        'pengaturan' => array_merge($desaLengkong->pengaturanDesa(), [
            'nama_tampilan' => 'Desa Lengkong',
            'slogan' => 'Desa Lengkong Maju & Tertib Data',
            'warna_tema' => 'emerald',
            'nama_kepala_desa' => 'H. Hendra Gunawan, S.IP.',
            'nip_kepala_desa' => '19780515 200604 1 009',
            'alamat_kantor' => 'Jl. Raya Lengkong No. 1, Bojongsoang, Kab. Bandung',
            'telepon_desa' => '(022) 87654321',
            'wajib_validasi_berjenjang' => true,
            'rw_lihat_penuh' => false,
            'arsip_otomatis_aktif' => true,
            'interval_arsip_jam' => 36,
        ]),
    ]);

    $rtIdsLengkong = \App\Models\Wilayah::withoutGlobalScopes()
        ->where('level', 'rt')
        ->where('path', 'like', $desaLengkong->path.'%')
        ->pluck('id')
        ->all();

    // Rekam Snapshot Periode #1 (Kondisi Awal)
    $snap1 = ArsipSnapshotDesa::rekamSnapshotDesa($desaLengkong->fresh(), $rtIdsLengkong, 36, 4);
    $snap1->forceFill(['created_at' => now()->subHours(37)])->saveQuietly();

    // 4. UBAH BEBERAPA HAL pada Keluarga Darmawan Putra di Desa Lengkong:
    // - Ubah alamat domisili & kategori ekonomi dari Rentan Miskin -> Menengah
    // - Ubah penghasilan Darmawan Putra dari 2.500.000 -> 4.800.000 & pekerjaan
    // - Tambah aset baru (Mobil - Toyota Avanza) & tambah anak baru (Rizky Pratama Putra)
    $kk->update(['alamat' => 'Jl. Raya Lengkong No. 45 Blok B2 (Renovasi Baru)']);
    $ekonomiModel = $kk->ekonomi()->first();
    if ($ekonomiModel) {
        $ekonomiModel->update(['kategori_ekonomi' => 'Menengah']);
    }
    $kk->aset()->updateOrCreate(
        ['jenis_aset' => 'Mobil'],
        ['keterangan' => '1 Unit Toyota Avanza', 'created_by' => $kades->id]
    );

    $kepala = $kk->anggota()->where('id_hubungan', 1)->first();
    if ($kepala) {
        $kepala->update([
            'penghasilan_bulanan' => 4800000,
            'id_pekerjaan' => 8, // Wiraswasta
        ]);
    }

    \App\Models\Anggota::updateOrCreate(
        ['id_keluarga' => $kk->id, 'nik_hash' => \App\Support\SensitiveData::hash('3204081212100011')],
        [
            'nik' => '3204081212100011',
            'nama_lengkap' => 'Rizky Pratama Putra',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2010-12-12',
            'jenis_kelamin' => 'L',
            'status_kawin' => 'belum_kawin',
            'status_kependudukan' => 'menetap',
            'id_agama' => 1,
            'id_pendidikan' => 3,
            'id_pekerjaan' => 1,
            'id_hubungan' => 3,
            'nama_ayah' => 'Darmawan Putra',
            'nama_ibu' => 'Ratna Ningsih',
            'penghasilan_bulanan' => 0,
            'created_by' => $kades->id,
        ]
    );

    // 5. Rekam Snapshot Batch #2 (Kondisi Sesudah Diubah)
    $snap2 = ArsipSnapshotDesa::rekamSnapshotDesa($desaLengkong->fresh(), $rtIdsLengkong, 36, 5);

    $this->info('Simulasi Desa Lengkong berhasil: Akun kades_lengkong dibuat, RT 02 & KK Darmawan Putra dibuat, diubah, dan 2 Batch Global (' . $snap1->kode_snapshot . ' vs ' . ($snap2?->kode_snapshot ?? '-') . ') berhasil direkam!');
})->purpose('Menjalankan simulasi pembuatan Kepala Desa Lengkong, RT, Keluarga, perubahan data, dan perbandingan 2 periode Snapshot Arsip');

Schedule::command('arsip:snapshot-otomatis')->hourly();
