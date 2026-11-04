<?php

namespace Database\Seeders;

use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Sumber data: https://github.com/cahyadsn/wilayah (MIT License).
// Copyright (c) 2025 cahya dsn. Data sesuai Kepmendagri No. 300.2.2-2430 Tahun 2025.
//
// Impor seluruh wilayah administratif nasional (provinsi, kabupaten/kota,
// kecamatan, desa/kelurahan) lalu buat pohon demo RW/RT di Bojongsoang.
// RW/RT/dusun tidak ada di dataset resmi sehingga tetap dibuat manual di sini.
class WilayahSeeder extends Seeder
{
    public const KODE_NEGARA = 'ID';

    public const KODE_PROVINSI = '32';

    public const KODE_KABUPATEN_BANDUNG = '32.04';

    public const KODE_KOTA_BANDUNG = '32.73';

    public const KODE_KECAMATAN_CONTOH = '32.04.08';

    public const KODE_DESA_CONTOH = '32.04.08.2002';

    /** Level administratif yang berasal dari dataset resmi. */
    private const LEVEL_RESMI = ['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'];

    public function run(): void
    {
        $jumlah = self::imporNasional();

        $this->command?->info(sprintf(
            'Impor wilayah: %d provinsi, %d kabupaten/kota, %d kecamatan, %d desa/kelurahan.',
            $jumlah['provinsi'] ?? 0,
            $jumlah['kabupaten_kota'] ?? 0,
            $jumlah['kecamatan'] ?? 0,
            $jumlah['desa'] ?? 0,
        ));

        self::buatDemo();
    }

    /**
     * Impor penuh lalu kembalikan node demo untuk DatabaseSeeder.
     *
     * @return array<string, mixed>
     */
    public static function pohon(): array
    {
        self::imporNasional();

        return self::buatDemo();
    }

    /**
     * Impor seluruh baris dataset ke tabel wilayah dan sinkronkan data lama.
     *
     * @return array<string, int>
     */
    public static function imporNasional(): array
    {
        $file = database_path('data/wilayah.sql');

        if (! is_file($file)) {
            throw new RuntimeException("Dataset wilayah tidak ditemukan: {$file}");
        }

        $baris = self::bacaSql($file);

        if ($baris === []) {
            throw new RuntimeException('Dataset wilayah tidak berisi baris yang bisa dibaca.');
        }

        return DB::transaction(function () use ($baris): array {
            $negara = Wilayah::firstOrCreate(
                ['parent_id' => null, 'kode' => self::KODE_NEGARA],
                ['nama' => 'Indonesia', 'level' => 'negara'],
            );

            $idPerKode = [self::KODE_NEGARA => $negara->id];
            $pathPerKode = [self::KODE_NEGARA => $negara->path ?: '/'.$negara->id.'/'];

            // Cache baris resmi yang sudah ada agar impor ulang tidak menembak
            // satu query pencarian per baris.
            $lama = DB::table('wilayah')
                ->whereIn('level', self::LEVEL_RESMI)
                ->get(['id', 'kode', 'path'])
                ->keyBy('kode');

            $sekarang = now();
            $jumlah = [];

            foreach ($baris as $row) {
                $indukId = $idPerKode[$row['induk']] ?? null;
                $indukPath = $pathPerKode[$row['induk']] ?? null;

                if ($indukId === null || $indukPath === null) {
                    throw new RuntimeException(
                        "Induk {$row['induk']} untuk kode {$row['kode']} tidak ditemukan."
                    );
                }

                $lamaBaris = $lama->get($row['kode']);
                $id = $lamaBaris?->id;

                $data = [
                    'kode' => $row['kode'],
                    'nama' => $row['nama'],
                    'level' => $row['level'],
                    'tipe' => $row['tipe'],
                    'parent_id' => $indukId,
                    'updated_at' => $sekarang,
                ];

                if ($id === null) {
                    $id = DB::table('wilayah')->insertGetId($data + ['created_at' => $sekarang]);
                } else {
                    DB::table('wilayah')->where('id', $id)->update($data);
                }

                $path = $indukPath.$id.'/';

                if ($lamaBaris?->path !== $path) {
                    DB::table('wilayah')->where('id', $id)->update(['path' => $path]);
                }

                $idPerKode[$row['kode']] = $id;
                $pathPerKode[$row['kode']] = $path;
                $jumlah[$row['level']] = ($jumlah[$row['level']] ?? 0) + 1;
            }

            return $jumlah;
        });
    }

    /**
     * @return list<array{kode: string, nama: string, level: string, tipe: ?string, induk: string}>
     */
    private static function bacaSql(string $file): array
    {
        $baris = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if (! preg_match("/^\('([0-9.]+)',\s*'(.*)'\)[,;]$/u", $line, $cocok)) {
                continue;
            }

            $kode = $cocok[1];
            $segmen = explode('.', $kode);

            $level = match (count($segmen)) {
                1 => 'provinsi',
                2 => 'kabupaten_kota',
                3 => 'kecamatan',
                4 => 'desa',
                default => null,
            };

            if ($level === null) {
                continue;
            }

            $baris[] = [
                'kode' => $kode,
                'nama' => str_replace("''", "'", $cocok[2]),
                'level' => $level,
                'tipe' => $level === 'desa'
                    ? (str_starts_with($segmen[3] ?? '', '1') ? 'kelurahan' : 'desa')
                    : null,
                'induk' => $level === 'provinsi'
                    ? self::KODE_NEGARA
                    : substr($kode, 0, (int) strrpos($kode, '.')),
            ];
        }

        return $baris;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buatDemo(): array
    {
        $negara = Wilayah::where('kode', self::KODE_NEGARA)->firstOrFail();
        $provinsi = Wilayah::where('kode', self::KODE_PROVINSI)->firstOrFail();
        $kabupaten = Wilayah::where('kode', self::KODE_KABUPATEN_BANDUNG)->firstOrFail();
        $kotaBandung = Wilayah::where('kode', self::KODE_KOTA_BANDUNG)->firstOrFail();
        $kecamatan = Wilayah::where('kode', self::KODE_KECAMATAN_CONTOH)->firstOrFail();
        $desa = Wilayah::where('kode', self::KODE_DESA_CONTOH)->firstOrFail();

        $dusun = Wilayah::firstOrCreate(
            ['parent_id' => $desa->id, 'kode' => 'DUSUN-KRAJAN'],
            ['nama' => 'Dusun Krajan', 'level' => 'dusun'],
        );

        $rw01 = Wilayah::firstOrCreate(
            ['parent_id' => $dusun->id, 'kode' => 'RW-01'],
            ['nama' => 'RW 01', 'level' => 'rw'],
        );

        $rw02 = Wilayah::firstOrCreate(
            ['parent_id' => $desa->id, 'kode' => 'RW-02'],
            ['nama' => 'RW 02', 'level' => 'rw'],
        );

        $rt = [];

        foreach (['01', '02'] as $nomor) {
            $rt["rw01-{$nomor}"] = Wilayah::firstOrCreate(
                ['parent_id' => $rw01->id, 'kode' => "RT-{$nomor}"],
                ['nama' => "RT {$nomor}", 'level' => 'rt'],
            );
        }

        foreach (['01', '02', '03'] as $nomor) {
            $rt["rw02-{$nomor}"] = Wilayah::firstOrCreate(
                ['parent_id' => $rw02->id, 'kode' => "RT-{$nomor}"],
                ['nama' => "RT {$nomor}", 'level' => 'rt'],
            );
        }

        // Jaga path node demo tetap konsisten bila path induk resmi berubah.
        foreach ([$dusun, $rw01, $rw02, ...array_values($rt)] as $node) {
            $path = ($node->parent?->path ?? '/').$node->id.'/';

            if ($node->path !== $path) {
                $node->forceFill(['path' => $path])->saveQuietly();
            }
        }

        return compact(
            'negara',
            'dusun',
            'rw01',
            'rw02',
            'rt',
        ) + [
            'provinsi' => $provinsi,
            'kabupaten' => $kabupaten,
            'kotaBandung' => $kotaBandung,
            'kecamatan' => $kecamatan,
            'desa' => $desa,
        ];
    }
}
