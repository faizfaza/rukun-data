<?php

namespace App\Models;

use App\Scopes\WilayahScope;
use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// PRD §8: no_kk/alamat tersimpan terenkripsi; akses properti mengembalikan
// plaintext sehingga dekripsi penuh hanya terjadi saat record dibaca (§7.5).
// $hidden mencegah plaintext ikut ke serialisasi (log/API/debug).
class Keluarga extends Model
{
    use SoftDeletes;

    protected $table = 'keluarga';

    protected $fillable = ['id_wilayah', 'no_kk', 'alamat', 'alamat_ktp', 'kode_pos', 'is_locked', 'created_by'];

    protected $hidden = ['no_kk', 'alamat', 'alamat_ktp'];

    protected static function booted(): void
    {
        static::addGlobalScope(new WilayahScope);
    }

    public function setNoKkAttribute(string $value): void
    {
        if ($this->exists && isset($this->attributes['no_kk']) && $this->no_kk === $value) {
            return;
        }

        $this->attributes['no_kk'] = SensitiveData::encrypt($value);
        $this->attributes['no_kk_hash'] = SensitiveData::hash(SensitiveData::digits($value));
    }

    public function getNoKkAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function getNoKkMaskedAttribute(): ?string
    {
        return $this->no_kk === null ? null : SensitiveData::mask($this->no_kk);
    }

    public function setAlamatAttribute(string $value): void
    {
        if ($this->exists && isset($this->attributes['alamat']) && $this->alamat === $value) {
            return;
        }

        $this->attributes['alamat'] = SensitiveData::encrypt($value);
    }

    public function getAlamatAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setAlamatKtpAttribute(?string $value): void
    {
        $normalized = ($value !== null && $value !== '') ? $value : null;
        if ($this->exists && array_key_exists('alamat_ktp', $this->attributes)) {
            $currentRaw = $this->attributes['alamat_ktp'];
            $currentDecrypted = ($currentRaw !== null && $currentRaw !== '') ? SensitiveData::decrypt($currentRaw) : null;
            if ($currentDecrypted === $normalized || ($currentDecrypted === null && $normalized === $this->alamat)) {
                return;
            }
        }

        $this->attributes['alamat_ktp'] = $normalized !== null
            ? SensitiveData::encrypt($normalized)
            : null;
    }

    public function getAlamatKtpAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $this->alamat;
        }

        return SensitiveData::decrypt($value);
    }

    public function isLocked(): bool
    {
        return (bool) ($this->is_locked ?? false);
    }

    // Pencarian persis via hash (PRD F7), termasuk baris soft-deleted agar
    // input ulang dapat menawarkan pemulihan (§8a), bukan duplikat baru.
    public static function findByNoKk(string $noKk): ?self
    {
        return static::withoutGlobalScopes()
            ->withTrashed()
            ->where('no_kk_hash', SensitiveData::hash(SensitiveData::digits($noKk)))
            ->first();
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(Anggota::class, 'id_keluarga');
    }

    public function anggotaTrashed(): HasMany
    {
        return $this->hasMany(Anggota::class, 'id_keluarga')->onlyTrashed();
    }

    // Dipakai kolom "Kepala keluarga" di daftar (id_hubungan kepala = 1).
    public function kepala(): HasOne
    {
        return $this->hasOne(Anggota::class, 'id_keluarga')
            ->where('id_hubungan', RefHubungan::KEPALA);
    }

    public function ekonomi(): HasMany
    {
        return $this->hasMany(EkonomiKeluarga::class, 'id_keluarga');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(AsetKeluarga::class, 'id_keluarga');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function riwayatAktivitas(): \Illuminate\Database\Eloquent\Builder
    {
        $anggotaIds = Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $this->id)->pluck('id')->all();
        $asetIds = AsetKeluarga::withoutGlobalScopes()->where('id_keluarga', $this->id)->pluck('id')->all();
        $ekonomiIds = EkonomiKeluarga::withoutGlobalScopes()->where('id_keluarga', $this->id)->pluck('id')->all();

        return AuditLog::with(['pengguna.wilayah'])
            ->where(function ($query) use ($anggotaIds, $asetIds, $ekonomiIds) {
                $query->where(function ($q) {
                    $q->where('nama_tabel', 'keluarga')
                        ->where('id_record', $this->id);
                });

                if (! empty($anggotaIds)) {
                    $query->orWhere(function ($q) use ($anggotaIds) {
                        $q->where('nama_tabel', 'anggota')
                            ->whereIn('id_record', $anggotaIds);
                    });
                }

                if (! empty($asetIds)) {
                    $query->orWhere(function ($q) use ($asetIds) {
                        $q->where('nama_tabel', 'aset_keluarga')
                            ->whereIn('id_record', $asetIds);
                    });
                }

                if (! empty($ekonomiIds)) {
                    $query->orWhere(function ($q) use ($ekonomiIds) {
                        $q->where('nama_tabel', 'ekonomi_keluarga')
                            ->whereIn('id_record', $ekonomiIds);
                    });
                }
            })
            ->orderBy('waktu', 'desc')
            ->orderBy('id', 'desc');
    }
}
