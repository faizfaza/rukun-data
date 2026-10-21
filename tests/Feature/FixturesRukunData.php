<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\Hash;

// Fixture bersama: ref, pohon wilayah lengkap (negara -> provinsi ->
// kabupaten_kota -> kecamatan -> desa -> rw -> rt1/rt2), dan pembuat
// keluarga/anggota minimal. NIK/No KK fiktif khusus pengujian.
trait FixturesRukunData
{
    /** @return array<string, Wilayah> */
    protected function seedDasar(): array
    {
        foreach (['Islam', 'Kristen'] as $nama) {
            RefAgama::create(['nama' => $nama]);
        }

        foreach (['SD', 'SMA/SMK', 'Sarjana'] as $nama) {
            RefPendidikan::create(['nama' => $nama]);
        }

        foreach (['Tidak Bekerja', 'Buruh', 'PNS'] as $nama) {
            RefPekerjaan::create(['nama' => $nama]);
        }

        foreach (['Kepala Keluarga', 'Istri', 'Anak'] as $nama) {
            RefHubungan::create(['nama' => $nama]);
        }

        $negara = Wilayah::create(['kode' => 'T', 'nama' => 'Negara Uji', 'level' => 'negara']);
        $provinsi = Wilayah::create(['kode' => 'T.1', 'nama' => 'Provinsi Uji', 'level' => 'provinsi', 'parent_id' => $negara->id]);
        $kabupaten = Wilayah::create(['kode' => 'T.1.1', 'nama' => 'Kabupaten Uji', 'level' => 'kabupaten_kota', 'parent_id' => $provinsi->id]);
        $kecamatan = Wilayah::create(['kode' => 'T.1.1.1', 'nama' => 'Kecamatan Uji', 'level' => 'kecamatan', 'parent_id' => $kabupaten->id]);
        $desa = Wilayah::create(['kode' => 'T.1.1.1.1', 'nama' => 'Desa Uji', 'level' => 'desa', 'tipe' => 'desa', 'parent_id' => $kecamatan->id]);
        $rw = Wilayah::create(['kode' => 'T.1.1.1.1.1', 'nama' => 'RW 001', 'level' => 'rw', 'parent_id' => $desa->id]);
        $rt1 = Wilayah::create(['kode' => 'T.1.1.1.1.1.1', 'nama' => 'RT 001', 'level' => 'rt', 'parent_id' => $rw->id]);
        $rt2 = Wilayah::create(['kode' => 'T.1.1.1.1.1.2', 'nama' => 'RT 002', 'level' => 'rt', 'parent_id' => $rw->id]);

        return compact('negara', 'provinsi', 'kabupaten', 'kecamatan', 'desa', 'rw', 'rt1', 'rt2');
    }

    protected function makeUser(string $username, string $role, ?int $wilayahId): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => "{$username}@uji.local",
            'password' => Hash::make('password'),
            'role' => $role,
            'id_wilayah' => $wilayahId,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{nik: string, hubungan?: int}>  $anggota
     */
    protected function makeKeluarga(int $rtId, string $noKk, array $anggota, int $by): Keluarga
    {
        $keluarga = Keluarga::create([
            'id_wilayah' => $rtId,
            'no_kk' => $noKk,
            'alamat' => 'Jl. Uji No. 1',
            'kode_pos' => '70000',
            'created_by' => $by,
        ]);

        foreach ($anggota as $i => $a) {
            Anggota::create([
                'id_keluarga' => $keluarga->id,
                'nik' => $a['nik'],
                'nama_lengkap' => "Anggota {$i}",
                'tempat_lahir' => 'Uji',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'status_kawin' => 'kawin',
                'id_agama' => 1,
                'id_pendidikan' => 1,
                'id_pekerjaan' => 1,
                'id_hubungan' => $a['hubungan'] ?? 3,
                'created_by' => $by,
            ]);
        }

        return $keluarga;
    }
}
