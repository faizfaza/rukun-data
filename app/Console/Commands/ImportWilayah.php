<?php

namespace App\Console\Commands;

use App\Models\Wilayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportWilayah extends Command
{
    protected $signature = 'wilayah:import {file : Snapshot JSON} {--dry-run} {--mapping= : JSON kode sumber ke ID wilayah lama}';

    protected $description = 'Validasi dan impor snapshot wilayah tanpa menghapus data lokal';

    public function handle(): int
    {
        try {
            $file = $this->argument('file');
            $raw = file_get_contents($file);
            $data = json_decode(str_ends_with($file, '.gz') ? gzdecode($raw) : $raw, true, 512, JSON_THROW_ON_ERROR);
            foreach (['source_url', 'version', 'license', 'rows'] as $key) {
                if (empty($data[$key])) {
                    throw new RuntimeException("Metadata $key wajib diisi.");
                }
            }
            $mapping = $this->option('mapping') ? json_decode(file_get_contents($this->option('mapping')), true, 512, JSON_THROW_ON_ERROR) : [];
            $seen = [];
            foreach ($data['rows'] as $row) {
                if (! is_string($row['code'] ?? null) || empty($row['name']) || ! in_array($row['level'] ?? '', ['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'])) {
                    throw new RuntimeException('Baris wilayah tidak valid. Kode harus string dan level sampai desa.');
                }
                if (isset($seen[$row['code']])) {
                    throw new RuntimeException('Kode duplikat: '.$row['code']);
                }
                $seen[$row['code']] = true;
            }
            $this->line('SHA256: '.hash_file('sha256', $file));
            // Seluruh snapshot atomik; validasi konflik juga dilaksanakan pada dry-run.
            DB::beginTransaction();
            $root = Wilayah::where('level', 'negara')->firstOrFail();
            $pending = $data['rows'];
            $existingIds = Wilayah::where('sumber', 'kemendagri')->pluck('id', 'kode_sumber')->all();
            $parentCodes = array_fill_keys(array_filter(array_column($pending, 'parent_code')), true);
            $parentCache = [];
            $resolved = [];
            $created = 0;
            $updated = 0;
            while ($pending) {
                $progress = false;
                foreach ($pending as $index => $row) {
                    $parentCode = $row['parent_code'] ?? null;
                    $parent = $parentCode === null ? $root : ($parentCache[$parentCode] ?? (isset($resolved[$parentCode]) ? Wilayah::find($resolved[$parentCode]) : (isset($existingIds[$parentCode]) ? Wilayah::find($existingIds[$parentCode]) : null)));
                    if (! $parent) {
                        continue;
                    }
                    $existing = isset($existingIds[$row['code']]) ? Wilayah::find($existingIds[$row['code']]) : null;
                    if (isset($mapping[$row['code']])) {
                        $mapped = Wilayah::findOrFail($mapping[$row['code']]);
                        if ($existing && ! $existing->is($mapped)) {
                            throw new RuntimeException('Mapping bertabrakan: '.$row['code']);
                        }
                        $existing = $mapped;
                    }
                    if ($existing && ($existing->parent_id !== $parent->id || $existing->level !== $row['level'])) {
                        throw new RuntimeException('Perubahan induk/level memerlukan rekonsiliasi: '.$row['code']);
                    }
                    if ($existing && $existing->kode_sumber && $existing->kode_sumber !== $row['code']) {
                        throw new RuntimeException('Mapping kode lama berbeda: '.$row['code']);
                    }
                    $values = ['kode' => $row['code'], 'nama' => $row['name'], 'level' => $row['level'], 'parent_id' => $parent->id, 'sumber' => 'kemendagri', 'kode_sumber' => $row['code'], 'versi_sumber' => $data['version']];
                    if ($existing) {
                        $existing->update($values);
                        $updated++;
                    } else {
                        $existing = Wilayah::create($values + ['is_active' => false]);
                        $created++;
                    }
                    $resolved[$row['code']] = $existing->id;
                    if (isset($parentCodes[$row['code']])) {
                        $parentCache[$row['code']] = $existing;
                    }
                    unset($pending[$index]);
                    $progress = true;
                }
                if (! $progress) {
                    throw new RuntimeException('Induk hilang atau siklus dalam snapshot.');
                }
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
            $this->info(($this->option('dry-run') ? 'DRY RUN: ' : '')."$created baru, $updated diperiksa/diperbarui; tidak ada penghapusan.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
