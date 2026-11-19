<?php

namespace App\Models;

use App\Support\SensitiveData;
use App\Support\WilayahAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ValidasiPerubahan extends Model
{
    public const STATUS_MENUNGGU_RW = 'menunggu_rw';

    public const STATUS_MENUNGGU_DESA = 'menunggu_desa';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'validasi_perubahan';

    protected $fillable = [
        'id_pengguna',
        'peran_pengaju',
        'aksi',
        'nama_tabel',
        'id_record',
        'id_keluarga',
        'id_wilayah_rt',
        'id_wilayah_rw',
        'id_wilayah_desa',
        'id_wilayah_tujuan',
        'status',
        'ringkasan',
        'catatan_pengaju',
        'payload',
        'id_verifikator_rw',
        'waktu_verifikasi_rw',
        'catatan_rw',
        'id_verifikator_desa',
        'waktu_verifikasi_desa',
        'catatan_desa',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'waktu_verifikasi_rw' => 'datetime',
            'waktu_verifikasi_desa' => 'datetime',
        ];
    }

    public function setPayloadAttribute(mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->attributes['payload'] = null;

            return;
        }

        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $this->attributes['payload'] = $json ? SensitiveData::encrypt($json) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayloadDataAttribute(): array
    {
        $raw = $this->attributes['payload'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decrypted = SensitiveData::decrypt($raw);
        if (! is_string($decrypted) || $decrypted === '') {
            return [];
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga')->withTrashed();
    }

    public function wilayahRt(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_rt');
    }

    public function wilayahRw(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_rw');
    }

    public function wilayahDesa(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_desa');
    }

    public function wilayahTujuan(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_tujuan');
    }

    public function verifikatorRw(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_verifikator_rw');
    }

    public function verifikatorDesa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_verifikator_desa');
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_MENUNGGU_RW => 'Menunggu Verifikasi RW',
            self::STATUS_MENUNGGU_DESA => 'Menunggu Verifikasi Desa',
            self::STATUS_DISETUJUI => 'Disetujui (Terverifikasi Desa)',
            self::STATUS_DITOLAK => 'Ditolak (Dikembalikan)',
            default => ucfirst($status),
        };
    }

    public static function warnaStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_MENUNGGU_RW => 'warning',
            self::STATUS_MENUNGGU_DESA => 'info',
            self::STATUS_DISETUJUI => 'success',
            self::STATUS_DITOLAK => 'danger',
            default => 'gray',
        };
    }

    public static function labelAksi(string $aksi): string
    {
        return match ($aksi) {
            'pindah' => 'Mutasi / Pindah',
            'created' => 'Penambahan Data',
            'updated' => 'Perubahan Data',
            'deleted' => 'Penghapusan / Arsip',
            'restored' => 'Pemulihan Arsip',
            default => ucfirst($aksi),
        };
    }

    public function bolehDiverifikasiRwOleh(User $user): bool
    {
        if ($this->status !== self::STATUS_MENUNGGU_RW) {
            return false;
        }

        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isAdminRw()) {
            if ($this->id_wilayah_rw && $user->id_wilayah === $this->id_wilayah_rw) {
                return true;
            }
            $allowedRt = WilayahAccess::allowedRtIds($user) ?? [];

            return $this->id_wilayah_rt !== null && in_array($this->id_wilayah_rt, $allowedRt, true);
        }

        return false;
    }

    public function bolehDiverifikasiDesaOleh(User $user): bool
    {
        // Jaga rantai komando (chain of command):
        // Pengajuan Admin RT wajib lewat verifikasi Admin RW (status menjadi menunggu_desa) terlebih dahulu.
        if ($this->status !== self::STATUS_MENUNGGU_DESA) {
            return false;
        }

        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isAdminDesa()) {
            if ($this->id_wilayah_desa && $user->id_wilayah === $this->id_wilayah_desa) {
                return true;
            }
            $allowedRt = WilayahAccess::allowedRtIds($user) ?? [];

            return $this->id_wilayah_rt !== null && in_array($this->id_wilayah_rt, $allowedRt, true);
        }

        return false;
    }

    public function bolehDitolakOleh(User $user): bool
    {
        if (! in_array($this->status, [self::STATUS_MENUNGGU_RW, self::STATUS_MENUNGGU_DESA], true)) {
            return false;
        }

        return $this->bolehDiverifikasiRwOleh($user) || $this->bolehDiverifikasiDesaOleh($user);
    }

    public function setujuiOlehRw(User $user, ?string $catatan = null): void
    {
        $this->update([
            'status' => self::STATUS_MENUNGGU_DESA,
            'id_verifikator_rw' => $user->id,
            'waktu_verifikasi_rw' => now(),
            'catatan_rw' => $catatan,
        ]);
    }

    public function setujuiOlehDesa(User $user, ?string $catatan = null): void
    {
        if ($this->status !== self::STATUS_MENUNGGU_DESA) {
            throw new \LogicException('Pengajuan harus diverifikasi oleh Admin RW terlebih dahulu sebelum disahkan oleh Desa.');
        }

        $updates = [
            'status' => self::STATUS_DISETUJUI,
            'id_verifikator_desa' => $user->id,
            'waktu_verifikasi_desa' => now(),
            'catatan_desa' => $catatan,
        ];

        if ($this->id_verifikator_rw === null) {
            $updates['id_verifikator_rw'] = $user->id;
            $updates['waktu_verifikasi_rw'] = now();
            $updates['catatan_rw'] = $this->catatan_rw ?: 'Diajukan pada tingkat RW (langsung ke Desa)';
        }

        $this->update($updates);

        $this->terapkanPerubahanSaatDisahkan();
        $this->terapkanDataPindahJikaAda();
    }

    /**
     * Menerapkan perubahan tertunda (staged) ke data resmi tepat setelah disahkan oleh Kepala Desa.
     */
    protected function terapkanPerubahanSaatDisahkan(): void
    {
        $payload = $this->payloadData;
        $rawAfter = $payload['raw_after'] ?? null;

        if ($this->aksi === 'created') {
            return;
        }

        $model = match ($this->nama_tabel) {
            'keluarga' => Keluarga::withoutGlobalScopes()->withTrashed()->find($this->id_record),
            'anggota' => Anggota::withoutGlobalScopes()->withTrashed()->find($this->id_record),
            'aset_keluarga' => AsetKeluarga::withoutGlobalScopes()->find($this->id_record),
            'ekonomi_keluarga' => EkonomiKeluarga::withoutGlobalScopes()->find($this->id_record),
            default => null,
        };

        if (! $model) {
            return;
        }

        if ($this->aksi === 'deleted') {
            Model::withoutEvents(fn () => $model->delete());

            return;
        }

        if (in_array($this->aksi, ['updated', 'pindah'], true) && is_array($rawAfter) && $rawAfter !== []) {
            $updateCols = $rawAfter;
            if ($model->usesTimestamps() && $model->getUpdatedAtColumn()) {
                $updateCols[$model->getUpdatedAtColumn()] = now();
            }

            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update($updateCols);
        }
    }

    public function tolak(User $user, string $alasan): void
    {
        $updates = [
            'status' => self::STATUS_DITOLAK,
        ];

        if ($this->status === self::STATUS_MENUNGGU_RW && $user->isAdminRw()) {
            $updates['id_verifikator_rw'] = $user->id;
            $updates['waktu_verifikasi_rw'] = now();
            $updates['catatan_rw'] = $alasan;
        } else {
            $updates['id_verifikator_desa'] = $user->id;
            $updates['waktu_verifikasi_desa'] = now();
            $updates['catatan_desa'] = $alasan;
        }

        $this->update($updates);

        $this->rollbackPerubahan();
    }

    protected function terapkanDataPindahJikaAda(): void
    {
        $payload = $this->payloadData;
        $dataPindah = $payload['data_pindah'] ?? null;

        if (! is_array($dataPindah)) {
            return;
        }

        $jenisMutasi = $dataPindah['jenis_mutasi'] ?? 'keluarga';
        $rtTujuan = isset($dataPindah['id_wilayah_tujuan']) ? (int) $dataPindah['id_wilayah_tujuan'] : null;
        $alamatBaru = $dataPindah['alamat_tujuan'] ?? null;

        if ($jenisMutasi === 'keluarga' && $this->id_keluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($this->id_keluarga);
            if ($keluarga) {
                if ($rtTujuan) {
                    $updates = ['id_wilayah' => $rtTujuan];
                    if (filled($alamatBaru)) {
                        $updates['alamat'] = $alamatBaru;
                    }
                    $keluarga->forceFill($updates)->saveQuietly();
                } else {
                    // Pindah keluar desa: tandai seluruh anggota sebagai pindah
                    Anggota::withoutGlobalScopes()
                        ->where('id_keluarga', $keluarga->id)
                        ->update(['status_kependudukan' => 'pindah']);
                }
            }
        } elseif ($jenisMutasi === 'anggota' && ! empty($dataPindah['id_anggota'])) {
            $anggota = Anggota::withoutGlobalScopes()->withTrashed()->find((int) $dataPindah['id_anggota']);
            if ($anggota) {
                $anggota->forceFill(['status_kependudukan' => 'pindah'])->saveQuietly();
            }
        }
    }

    public function rollbackPerubahan(): void
    {
        $payload = $this->payloadData;
        $rawBefore = $payload['raw_before'] ?? null;

        // Jika perubahan memang ditahan (deferred) dan belum pernah diterapkan ke data utama,
        // hanya aksi 'created' (record baru yang masih tersembunyi) yang perlu dihapus/diarsipkan.
        if (! empty($payload['deferred']) && $this->aksi !== 'created') {
            return;
        }

        if ($this->nama_tabel === 'keluarga') {
            $model = Keluarga::withoutGlobalScopes()->withTrashed()->find($this->id_record);
            if (! $model) {
                return;
            }
            if ($this->aksi === 'created') {
                Model::withoutEvents(fn () => $model->delete());
            } elseif ($this->aksi === 'deleted') {
                Model::withoutEvents(fn () => $model->restore());
            } elseif (is_array($rawBefore) && $rawBefore !== []) {
                DB::table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->update($rawBefore);
            }

            return;
        }

        if ($this->nama_tabel === 'anggota') {
            $model = Anggota::withoutGlobalScopes()->withTrashed()->find($this->id_record);
            if (! $model) {
                return;
            }
            if ($this->aksi === 'created') {
                Model::withoutEvents(fn () => $model->delete());
            } elseif ($this->aksi === 'deleted') {
                Model::withoutEvents(fn () => $model->restore());
            } elseif (is_array($rawBefore) && $rawBefore !== []) {
                DB::table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->update($rawBefore);
            }

            return;
        }

        if ($this->nama_tabel === 'aset_keluarga') {
            $model = AsetKeluarga::withoutGlobalScopes()->find($this->id_record);
            if ($this->aksi === 'created' && $model) {
                Model::withoutEvents(fn () => $model->delete());
            } elseif ($this->aksi === 'deleted' && ! $model && is_array($rawBefore) && $rawBefore !== []) {
                DB::table('aset_keluarga')->insert($rawBefore);
            } elseif (is_array($rawBefore) && $rawBefore !== [] && $model) {
                DB::table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->update($rawBefore);
            }

            return;
        }

        if ($this->nama_tabel === 'ekonomi_keluarga') {
            $model = EkonomiKeluarga::withoutGlobalScopes()->find($this->id_record);
            if ($this->aksi === 'created' && $model) {
                Model::withoutEvents(fn () => $model->delete());
            } elseif ($this->aksi === 'deleted' && ! $model && is_array($rawBefore) && $rawBefore !== []) {
                DB::table('ekonomi_keluarga')->insert($rawBefore);
            } elseif (is_array($rawBefore) && $rawBefore !== [] && $model) {
                DB::table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->update($rawBefore);
            }
        }
    }

    public function labelKeluargaTerkait(): string
    {
        $payload = $this->payloadData;
        $konteks = $payload['konteks'] ?? [];
        $sesudah = $payload['sesudah'] ?? [];
        $sebelum = $payload['sebelum'] ?? [];

        if (! empty($sesudah['Kartu Keluarga (Kepala)'])) {
            return 'KK '.$sesudah['Kartu Keluarga (Kepala)'];
        }
        if (! empty($sesudah['Milik Keluarga (Kepala KK)'])) {
            return 'KK '.$sesudah['Milik Keluarga (Kepala KK)'];
        }
        if (! empty($sesudah['Kepala Keluarga'])) {
            return 'KK '.$sesudah['Kepala Keluarga'];
        }
        if (! empty($sebelum['Kepala Keluarga'])) {
            return 'KK '.$sebelum['Kepala Keluarga'];
        }
        if (! empty($konteks['nama_subjek'])) {
            return (string) $konteks['nama_subjek'];
        }

        return $this->id_keluarga ? 'KK #'.$this->id_keluarga : 'Data #'.$this->id_record;
    }
}

