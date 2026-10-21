<?php

namespace Database\Seeders;

use App\Models\Wilayah;
use Illuminate\Database\Seeder;

// Seeder wilayah contoh (Fase 1.4): satu rantai dari Indonesia sampai RT.
//
// PENTING: seluruh kode di bawah adalah KODE CONTOH, bukan kode resmi
// Kemendagri. Nantinya akan diganti dari data resmi (JSON Kemendagri).
class WilayahContohSeeder extends Seeder
{
    public function run(): void
    {
        self::pohon();
    }

    // Membangun pohon contoh dan mengembalikan model-modelnya agar seeder lain
    // (DatabaseSeeder) bisa memakai id-nya. Struktur menguji dusun opsional:
    // RW 01 berada di dalam Dusun, RW 02 langsung di bawah Desa.
    /**
     * @return array<string, mixed>
     */
    public static function pohon(): array
    {
        $negara = Wilayah::create(['kode' => 'ID', 'nama' => 'Indonesia', 'level' => 'negara']);

        $provinsi = Wilayah::create([
            'kode' => 'CONTOH-PROV', 'nama' => 'Jawa Barat', 'level' => 'provinsi', 'parent_id' => $negara->id,
        ]);

        $kabupaten = Wilayah::create([
            'kode' => 'CONTOH-KAB', 'nama' => 'Kabupaten Bandung', 'level' => 'kabupaten_kota', 'parent_id' => $provinsi->id,
        ]);

        $kecamatan = Wilayah::create([
            'kode' => 'CONTOH-KEC', 'nama' => 'Kecamatan Bojongsoang', 'level' => 'kecamatan', 'parent_id' => $kabupaten->id,
        ]);

        $desa = Wilayah::create([
            'kode' => 'CONTOH-DESA', 'nama' => 'Desa Bojongsoang', 'level' => 'desa', 'tipe' => 'desa', 'parent_id' => $kecamatan->id,
        ]);

        $dusun = Wilayah::create([
            'kode' => 'CONTOH-DUSUN', 'nama' => 'Dusun Krajan', 'level' => 'dusun', 'parent_id' => $desa->id,
        ]);

        $rw01 = Wilayah::create(['kode' => 'RW-01', 'nama' => 'RW 01', 'level' => 'rw', 'parent_id' => $dusun->id]);
        $rw02 = Wilayah::create(['kode' => 'RW-02', 'nama' => 'RW 02', 'level' => 'rw', 'parent_id' => $desa->id]);

        $rt = [];

        foreach (['01', '02'] as $nomor) {
            $rt["rw01-{$nomor}"] = Wilayah::create([
                'kode' => "RT-{$nomor}", 'nama' => "RT {$nomor}", 'level' => 'rt', 'parent_id' => $rw01->id,
            ]);
        }

        foreach (['01', '02', '03'] as $nomor) {
            $rt["rw02-{$nomor}"] = Wilayah::create([
                'kode' => "RT-{$nomor}", 'nama' => "RT {$nomor}", 'level' => 'rt', 'parent_id' => $rw02->id,
            ]);
        }

        return compact('negara', 'provinsi', 'kabupaten', 'kecamatan', 'desa', 'dusun', 'rw01', 'rw02', 'rt');
    }
}
