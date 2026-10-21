<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Anggota;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Keputusan 4/6: akun contoh untuk tiga peran. Password dev semuanya
// "password". Data keluarga/anggota tetap dummy fiktif (PRD §11).
// Tanpa WithoutModelEvents agar mutator enkripsi dan observer tetap jalan;
// kepala_lock diisi eksplisit karena hook `saving` nonaktif saat seeding.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRefs();
        $wilayah = WilayahContohSeeder::pohon();
        $users = $this->seedUsers($wilayah);
        $this->seedKeluarga($wilayah, $users);
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
     * @param  array<string, mixed>  $wilayah
     * @return array<string, User>
     */
    private function seedUsers(array $wilayah): array
    {
        $users = [];

        $users['superadmin'] = $this->buatUser('superadmin', 'Superadmin', Role::Superadmin, null);

        $users['adminrw01'] = $this->buatUser('adminrw01', 'Admin RW 01', Role::AdminRw, $wilayah['rw01']->id, $users['superadmin']->id);
        $users['adminrw02'] = $this->buatUser('adminrw02', 'Admin RW 02', Role::AdminRw, $wilayah['rw02']->id, $users['superadmin']->id);

        $petaRt = [
            'adminrt0101' => ['rw01-01', 'adminrw01'],
            'adminrt0102' => ['rw01-02', 'adminrw01'],
            'adminrt0201' => ['rw02-01', 'adminrw02'],
            'adminrt0202' => ['rw02-02', 'adminrw02'],
            'adminrt0203' => ['rw02-03', 'adminrw02'],
        ];

        foreach ($petaRt as $username => [$rtKey, $pembuat]) {
            $nomor = substr($rtKey, -5);

            $users[$username] = $this->buatUser(
                $username,
                'Admin RT '.substr($nomor, 3).' RW '.substr($nomor, 0, 2),
                Role::AdminRt,
                $wilayah['rt'][$rtKey]->id,
                $users[$pembuat]->id,
            );
        }

        return $users;
    }

    private function buatUser(string $username, string $nama, Role $role, ?int $idWilayah, ?int $createdBy = null): User
    {
        return User::create([
            'name' => $nama,
            'username' => $username,
            'email' => "{$username}@rukundata.local",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'id_wilayah' => $idWilayah,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $wilayah
     * @param  array<string, User>  $users
     */
    private function seedKeluarga(array $wilayah, array $users): void
    {
        $kk = fn (string $rt, string $no, string $alamat, array $anggota, string $ekonomi) => [
            'rt' => $rt, 'no_kk' => $no, 'alamat' => $alamat, 'anggota' => $anggota, 'ekonomi' => $ekonomi,
        ];

        // Format anggota: [nik, nama, tempat, tanggal, jk, kawin, agama, didik, kerja, hub, ayah, ibu, gaji]
        $data = [
            $kk('rw01-01', '6371010101000001', 'Jl. Mawar No. 10', [
                ['6371010101800001', 'Budi Santoso', 'Banjarbaru', '1980-01-01', 'L', 'kawin', 1, 4, 8, 1, 'Ahmad Santoso', 'Siti Rahayu', '4500000'],
                ['6371010101850002', 'Dewi Lestari', 'Banjarbaru', '1985-01-01', 'P', 'kawin', 1, 4, 7, 2, 'Hadi Lestari', 'Rina Wulandari', '3800000'],
                ['6371010101080003', 'Andi Pratama', 'Banjarbaru', '2008-01-01', 'L', 'belum_kawin', 1, 3, 1, 3, 'Budi Santoso', 'Dewi Lestari', null],
                ['6371010101120004', 'Sinta Maharani', 'Banjarbaru', '2012-01-01', 'P', 'belum_kawin', 1, 2, 1, 3, 'Budi Santoso', 'Dewi Lestari', null],
            ], 'Menengah'),
            $kk('rw01-02', '6371010101000002', 'Jl. Mawar No. 12', [
                ['6371010101750005', 'Agus Setiawan', 'Martapura', '1975-01-01', 'L', 'kawin', 1, 3, 3, 1, 'Joko Setiawan', 'Sri Mulyani', '2800000'],
                ['6371010101780006', 'Rina Marlina', 'Martapura', '1978-01-01', 'P', 'kawin', 1, 3, 4, 2, 'Udin Marlina', 'Eni Sulastri', '1500000'],
                ['6371010101050007', 'Dedi Kurniawan', 'Banjarbaru', '2005-01-01', 'L', 'belum_kawin', 1, 4, 1, 3, 'Agus Setiawan', 'Rina Marlina', null],
            ], 'Rentan Miskin'),
            $kk('rw02-01', '6371010101000003', 'Jl. Melati No. 5', [
                ['6371010101700008', 'Hendra Wijaya', 'Banjarmasin', '1970-01-01', 'L', 'kawin', 2, 6, 5, 1, 'Wijaya Kusuma', 'Ratna Sari', '8000000'],
                ['6371010101720009', 'Maria Ulfa', 'Banjarmasin', '1972-01-01', 'P', 'kawin', 2, 6, 7, 2, 'Petrus Ulfa', 'Yohana Dewi', '6500000'],
                ['6371010101000010', 'Kevin Saputra', 'Banjarbaru', '2000-01-01', 'L', 'belum_kawin', 2, 6, 6, 3, 'Hendra Wijaya', 'Maria Ulfa', '5000000'],
                ['6371010101030011', 'Nadia Putri', 'Banjarbaru', '2003-01-01', 'P', 'belum_kawin', 2, 4, 1, 3, 'Hendra Wijaya', 'Maria Ulfa', null],
            ], 'Mampu'),
            $kk('rw02-02', '6371010101000004', 'Jl. Anggrek No. 8', [
                ['6371010101820012', 'Samsul Bahri', 'Banjarbaru', '1982-01-01', 'L', 'kawin', 1, 2, 2, 1, 'Bahri Ansori', 'Halimah', '2200000'],
                ['6371010101840013', 'Fitri Handayani', 'Banjarbaru', '1984-01-01', 'P', 'kawin', 1, 2, 1, 2, 'Handayani Saputra', 'Nur Aisyah', null],
                ['6371010101100014', 'Rizky Ramadhan', 'Banjarbaru', '2010-01-01', 'L', 'belum_kawin', 1, 3, 1, 3, 'Samsul Bahri', 'Fitri Handayani', null],
            ], 'Menengah'),
            $kk('rw02-03', '6371010101000005', 'Jl. Kenanga No. 3', [
                ['6371010101680015', 'Mahrani Saleh', 'Pelaihari', '1968-01-01', 'P', 'cerai_mati', 1, 2, 4, 1, 'Salehudin', 'Masniah', '1800000'],
                ['6371010101950016', 'Aulia Rahman', 'Banjarbaru', '1995-01-01', 'L', 'belum_kawin', 1, 4, 3, 3, '-', 'Mahrani Saleh', '2000000'],
            ], 'Miskin'),
        ];

        $pembuatRt = [
            'rw01-01' => 'adminrt0101',
            'rw01-02' => 'adminrt0102',
            'rw02-01' => 'adminrt0201',
            'rw02-02' => 'adminrt0202',
            'rw02-03' => 'adminrt0203',
        ];

        foreach ($data as $row) {
            $createdBy = $users[$pembuatRt[$row['rt']]]->id;

            DB::transaction(function () use ($row, $wilayah, $createdBy): void {
                $keluarga = Keluarga::create([
                    'id_wilayah' => $wilayah['rt'][$row['rt']]->id,
                    'no_kk' => $row['no_kk'],
                    'alamat' => $row['alamat'],
                    'kode_pos' => '40362',
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
