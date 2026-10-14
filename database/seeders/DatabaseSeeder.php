<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// PRD §11: data sampling dummy berstruktur realistis (5 keluarga, 16
// anggota). Seluruh NIK/No KK FIKTIF dan hanya lolos validasi format.
// Tanpa WithoutModelEvents agar mutator enkripsi tetap jalan; kepala_lock
// diisi eksplisit karena hook `saving` nonaktif saat seeding.
// Akun seeder (password: "password"): admin, petugas-desa, petugas-rt1..4.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRefs();
        $wilayah = $this->seedWilayah();
        $users = $this->seedUsers($wilayah);
        $this->seedKeluarga($wilayah, $users['admin']->id);
    }

    private function seedRefs(): void
    {
        foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $nama) {
            RefAgama::create(['nama' => $nama]);
        }

        foreach (['Tidak Sekolah', 'SD', 'SMP', 'SMA/SMK', 'Diploma', 'Sarjana', 'Pascasarjana'] as $nama) {
            RefPendidikan::create(['nama' => $nama]);
        }

        foreach (['Tidak Bekerja', 'Petani', 'Buruh', 'Pedagang', 'PNS', 'Karyawan Swasta', 'Guru', 'Wiraswasta'] as $nama) {
            RefPekerjaan::create(['nama' => $nama]);
        }

        // Urutan disengaja: id 1 = Kepala Keluarga (RefHubungan::KEPALA).
        foreach (['Kepala Keluarga', 'Istri', 'Anak', 'Menantu', 'Cucu', 'Orang Tua', 'Famili Lain'] as $nama) {
            RefHubungan::create(['nama' => $nama]);
        }
    }

    /**
     * @return array{desa: Wilayah, rt: array<string, Wilayah>}
     */
    private function seedWilayah(): array
    {
        $desa = Wilayah::create(['kode' => '63.71.01.2001', 'nama' => 'Desa Sungai Besar', 'level' => 'desa']);

        $rt = [];

        foreach (['001' => ['001', '002'], '002' => ['003', '004']] as $rw => $rts) {
            $rwModel = Wilayah::create([
                'kode' => "63.71.01.2001.{$rw}",
                'nama' => "RW {$rw}",
                'level' => 'rw',
                'parent_id' => $desa->id,
            ]);

            foreach ($rts as $nomor) {
                $rt[$nomor] = Wilayah::create([
                    'kode' => "63.71.01.2001.{$rw}.{$nomor}",
                    'nama' => "RT {$nomor}",
                    'level' => 'rt',
                    'parent_id' => $rwModel->id,
                ]);
            }
        }

        return ['desa' => $desa, 'rt' => $rt];
    }

    /**
     * @param  array{desa: Wilayah, rt: array<string, Wilayah>}  $wilayah
     * @return array<string, User>
     */
    private function seedUsers(array $wilayah): array
    {
        $users = [];

        $users['admin'] = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@rukundata.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'id_wilayah' => null,
        ]);

        $users['desa'] = User::create([
            'name' => 'Petugas Desa',
            'username' => 'petugas-desa',
            'email' => 'desa@rukundata.local',
            'password' => Hash::make('password'),
            'role' => 'desa',
            'id_wilayah' => $wilayah['desa']->id,
        ]);

        foreach ($wilayah['rt'] as $nomor => $rt) {
            $users["rt{$nomor}"] = User::create([
                'name' => "Petugas RT {$nomor}",
                'username' => "petugas-rt{$nomor}",
                'email' => "rt{$nomor}@rukundata.local",
                'password' => Hash::make('password'),
                'role' => 'rt_rw',
                'id_wilayah' => $rt->id,
            ]);
        }

        return $users;
    }

    /**
     * @param  array{desa: Wilayah, rt: array<string, Wilayah>}  $wilayah
     */
    private function seedKeluarga(array $wilayah, int $createdBy): void
    {
        $kk = fn (string $rt, string $no, string $alamat, array $anggota, string $ekonomi) => [
            'rt' => $rt, 'no_kk' => $no, 'alamat' => $alamat, 'anggota' => $anggota, 'ekonomi' => $ekonomi,
        ];

        // Format anggota: [nik, nama, tempat, tanggal, jk, kawin, agama, didik, kerja, hub, ayah, ibu, gaji]
        $data = [
            $kk('001', '6371010101000001', 'Jl. Mawar No. 10', [
                ['6371010101800001', 'Budi Santoso', 'Banjarbaru', '1980-01-01', 'L', 'kawin', 1, 4, 8, 1, 'Ahmad Santoso', 'Siti Rahayu', '4500000'],
                ['6371010101850002', 'Dewi Lestari', 'Banjarbaru', '1985-01-01', 'P', 'kawin', 1, 4, 7, 2, 'Hadi Lestari', 'Rina Wulandari', '3800000'],
                ['6371010101080003', 'Andi Pratama', 'Banjarbaru', '2008-01-01', 'L', 'belum_kawin', 1, 3, 1, 3, 'Budi Santoso', 'Dewi Lestari', null],
                ['6371010101120004', 'Sinta Maharani', 'Banjarbaru', '2012-01-01', 'P', 'belum_kawin', 1, 2, 1, 3, 'Budi Santoso', 'Dewi Lestari', null],
            ], 'Menengah'),
            $kk('001', '6371010101000002', 'Jl. Mawar No. 12', [
                ['6371010101750005', 'Agus Setiawan', 'Martapura', '1975-01-01', 'L', 'kawin', 1, 3, 3, 1, 'Joko Setiawan', 'Sri Mulyani', '2800000'],
                ['6371010101780006', 'Rina Marlina', 'Martapura', '1978-01-01', 'P', 'kawin', 1, 3, 4, 2, 'Udin Marlina', 'Eni Sulastri', '1500000'],
                ['6371010101050007', 'Dedi Kurniawan', 'Banjarbaru', '2005-01-01', 'L', 'belum_kawin', 1, 4, 1, 3, 'Agus Setiawan', 'Rina Marlina', null],
            ], 'Rentan Miskin'),
            $kk('002', '6371010101000003', 'Jl. Melati No. 5', [
                ['6371010101700008', 'Hendra Wijaya', 'Banjarmasin', '1970-01-01', 'L', 'kawin', 2, 6, 5, 1, 'Wijaya Kusuma', 'Ratna Sari', '8000000'],
                ['6371010101720009', 'Maria Ulfa', 'Banjarmasin', '1972-01-01', 'P', 'kawin', 2, 6, 7, 2, 'Petrus Ulfa', 'Yohana Dewi', '6500000'],
                ['6371010101000010', 'Kevin Saputra', 'Banjarbaru', '2000-01-01', 'L', 'belum_kawin', 2, 6, 6, 3, 'Hendra Wijaya', 'Maria Ulfa', '5000000'],
                ['6371010101030011', 'Nadia Putri', 'Banjarbaru', '2003-01-01', 'P', 'belum_kawin', 2, 4, 1, 3, 'Hendra Wijaya', 'Maria Ulfa', null],
            ], 'Mampu'),
            $kk('003', '6371010101000004', 'Jl. Anggrek No. 8', [
                ['6371010101820012', 'Samsul Bahri', 'Banjarbaru', '1982-01-01', 'L', 'kawin', 1, 2, 2, 1, 'Bahri Ansori', 'Halimah', '2200000'],
                ['6371010101840013', 'Fitri Handayani', 'Banjarbaru', '1984-01-01', 'P', 'kawin', 1, 2, 1, 2, 'Handayani Saputra', 'Nur Aisyah', null],
                ['6371010101100014', 'Rizky Ramadhan', 'Banjarbaru', '2010-01-01', 'L', 'belum_kawin', 1, 3, 1, 3, 'Samsul Bahri', 'Fitri Handayani', null],
            ], 'Menengah'),
            $kk('004', '6371010101000005', 'Jl. Kenanga No. 3', [
                ['6371010101680015', 'Mahrani Saleh', 'Pelaihari', '1968-01-01', 'P', 'cerai_mati', 1, 2, 4, 1, 'Salehudin', 'Masniah', '1800000'],
                ['6371010101950016', 'Aulia Rahman', 'Banjarbaru', '1995-01-01', 'L', 'belum_kawin', 1, 4, 3, 3, '-', 'Mahrani Saleh', '2000000'],
            ], 'Miskin'),
        ];

        foreach ($data as $row) {
            DB::transaction(function () use ($row, $wilayah, $createdBy) {
                $keluarga = Keluarga::create([
                    'id_wilayah' => $wilayah['rt'][$row['rt']]->id,
                    'no_kk' => $row['no_kk'],
                    'alamat' => $row['alamat'],
                    'kode_pos' => '70711',
                    'created_by' => $createdBy,
                ]);

                foreach ($row['anggota'] as $a) {
                    $anggota = Anggota::create([
                        'id_keluarga' => $keluarga->id,
                        'nik' => $a[0],
                        'nama_lengkap' => $a[1],
                        'tempat_lahir' => $a[2],
                        'tanggal_lahir' => $a[3],
                        'jenis_kelamin' => $a[4],
                        'status_kawin' => $a[5],
                        'id_agama' => $a[6],
                        'id_pendidikan' => $a[7],
                        'id_pekerjaan' => $a[8],
                        'id_hubungan' => $a[9],
                        'nama_ayah' => $a[10],
                        'nama_ibu' => $a[11],
                        'penghasilan_bulanan' => $a[12],
                        'created_by' => $createdBy,
                    ]);

                    if ((int) $a[9] === RefHubungan::KEPALA) {
                        $anggota->forceFill(['kepala_lock' => $keluarga->id])->saveQuietly();
                    }
                }

                EkonomiKeluarga::create([
                    'id_keluarga' => $keluarga->id,
                    'tahun_data' => 2026,
                    'kategori_ekonomi' => $row['ekonomi'],
                    'created_by' => $createdBy,
                ]);
            });
        }
    }
}
