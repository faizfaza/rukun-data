<?php

namespace Database\Seeders;

use App\Models\Wilayah;
use Illuminate\Database\Seeder;

// Seeder wilayah contoh (Fase 1.4, diperluas agar tiap langkah pemilih punya
// beberapa opsi). Satu rantai utama Bojongsoang tetap dipertahankan karena
// dipakai DatabaseSeeder, ditambah cabang lain pada tiap level.
//
// PENTING: seluruh kode di bawah adalah KODE CONTOH, bukan kode resmi
// Kemendagri. Nantinya akan diganti dari data resmi (JSON Kemendagri).
class WilayahContohSeeder extends Seeder
{
    public function run(): void
    {
        self::pohon();
    }

    /**
     * @return array<string, mixed>
     */
    public static function pohon(): array
    {
        $negara = Wilayah::create(['kode' => 'ID', 'nama' => 'Indonesia', 'level' => 'negara']);

        // ---- Provinsi ------------------------------------------------------
        $provinsi = self::buat('CONTOH-PROV-JB', 'Jawa Barat', 'provinsi', $negara);

        $provinsiJateng = self::buat('CONTOH-PROV-JT', 'Jawa Tengah', 'provinsi', $negara);

        // ---- Kabupaten/Kota ------------------------------------------------
        $kabupaten = self::buat('CONTOH-KAB-BANDUNG', 'Kabupaten Bandung', 'kabupaten_kota', $provinsi);

        $kotaBandung = self::buat('CONTOH-KOTA-BANDUNG', 'Kota Bandung', 'kabupaten_kota', $provinsi);

        $kabupatenSemarang = self::buat('CONTOH-KAB-SEMARANG', 'Kabupaten Semarang', 'kabupaten_kota', $provinsiJateng);

        // ---- Kecamatan -----------------------------------------------------
        $kecamatan = self::buat('CONTOH-KEC-BOJONGSOANG', 'Kecamatan Bojongsoang', 'kecamatan', $kabupaten);

        $kecDayeuhkolot = self::buat('CONTOH-KEC-DAYEUHKOLOT', 'Kecamatan Dayeuhkolot', 'kecamatan', $kabupaten);

        $kecMargahayu = self::buat('CONTOH-KEC-MARGAHAYU', 'Kecamatan Margahayu', 'kecamatan', $kabupaten);

        $kecCoblong = self::buat('CONTOH-KEC-COBLONG', 'Kecamatan Coblong', 'kecamatan', $kotaBandung);

        $kecUngaran = self::buat('CONTOH-KEC-UNgaran', 'Kecamatan Ungaran', 'kecamatan', $kabupatenSemarang);

        // ---- Desa / Kelurahan ----------------------------------------------
        $desa = self::buat('CONTOH-DESA-BOJONGSOANG', 'Desa Bojongsoang', 'desa', $kecamatan, 'desa');

        $desaLengkong = self::buat('CONTOH-DESA-LENGKONG', 'Desa Lengkong', 'desa', $kecamatan, 'desa');

        $desaCiteureup = self::buat('CONTOH-DESA-CITEUREUP', 'Desa Citeureup', 'desa', $kecDayeuhkolot, 'desa');

        $desaMargahayu = self::buat('CONTOH-DESA-MARGAHAYU', 'Desa Margahayu', 'desa', $kecMargahayu, 'desa');

        $kelurahanDago = self::buat('CONTOH-KEL-DAGO', 'Kelurahan Dago', 'desa', $kecCoblong, 'kelurahan');

        $desaUngaran = self::buat('CONTOH-DESA-UNgaran', 'Desa Ungaran', 'desa', $kecUngaran, 'desa');

        // ---- Dusun (opsional) ----------------------------------------------
        $dusun = self::buat('CONTOH-DUSUN-KRAJAN', 'Dusun Krajan', 'dusun', $desa);

        // ---- RW ------------------------------------------------------------
        $rw01 = self::buat('RW-01', 'RW 01', 'rw', $dusun);
        $rw02 = self::buat('RW-02', 'RW 02', 'rw', $desa);
        $rwLengkong01 = self::buat('RW-01', 'RW 01', 'rw', $desaLengkong);
        $rwLengkong02 = self::buat('RW-02', 'RW 02', 'rw', $desaLengkong);
        $rwCiteureup01 = self::buat('RW-01', 'RW 01', 'rw', $desaCiteureup);
        $rwMargahayu01 = self::buat('RW-01', 'RW 01', 'rw', $desaMargahayu);
        $rwDago01 = self::buat('RW-01', 'RW 01', 'rw', $kelurahanDago);
        $rwUngaran01 = self::buat('RW-01', 'RW 01', 'rw', $desaUngaran);

        // ---- RT ------------------------------------------------------------
        // Kunci rt["rw01-xx"] dipakai DatabaseSeeder untuk menempatkan keluarga.
        $rt = [];

        foreach (['01', '02'] as $nomor) {
            $rt["rw01-{$nomor}"] = self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rw01);
        }

        foreach (['01', '02', '03'] as $nomor) {
            $rt["rw02-{$nomor}"] = self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rw02);
        }

        $rtLengkong = [];

        foreach ([$rwLengkong01, $rwLengkong02] as $i => $rw) {
            foreach (['01', '02'] as $nomor) {
                $rtLengkong['lengkong-'.($i + 1)."-{$nomor}"] = self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rw);
            }
        }

        $rtCiteureup = collect(['01', '02'])
            ->mapWithKeys(fn (string $nomor): array => [
                "citeureup-{$nomor}" => self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rwCiteureup01),
            ])->all();

        $rtMargahayu = collect(['01', '02'])
            ->mapWithKeys(fn (string $nomor): array => [
                "margahayu-{$nomor}" => self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rwMargahayu01),
            ])->all();

        $rtDago = collect(['01', '02'])
            ->mapWithKeys(fn (string $nomor): array => [
                "dago-{$nomor}" => self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rwDago01),
            ])->all();

        $rtUngaran = collect(['01', '02'])
            ->mapWithKeys(fn (string $nomor): array => [
                "ungaran-{$nomor}" => self::buat("RT-{$nomor}", "RT {$nomor}", 'rt', $rwUngaran01),
            ])->all();

        return compact(
            'negara', 'provinsi', 'provinsiJateng',
            'kabupaten', 'kotaBandung', 'kabupatenSemarang',
            'kecamatan', 'kecDayeuhkolot', 'kecMargahayu', 'kecCoblong', 'kecUngaran',
            'desa', 'desaLengkong', 'desaCiteureup', 'desaMargahayu', 'kelurahanDago', 'desaUngaran',
            'dusun',
            'rw01', 'rw02', 'rwLengkong01', 'rwLengkong02', 'rwCiteureup01', 'rwMargahayu01', 'rwDago01', 'rwUngaran01',
            'rt', 'rtLengkong', 'rtCiteureup', 'rtMargahayu', 'rtDago', 'rtUngaran',
        );
    }

    private static function buat(string $kode, string $nama, string $level, Wilayah $induk, ?string $tipe = null): Wilayah
    {
        return Wilayah::create([
            'kode' => $kode,
            'nama' => $nama,
            'level' => $level,
            'tipe' => $tipe,
            'parent_id' => $induk->id,
        ]);
    }
}
