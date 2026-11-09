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
        $this->attributes['alamat'] = SensitiveData::encrypt($value);
    }

    public function getAlamatAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setAlamatKtpAttribute(?string $value): void
    {
        $this->attributes['alamat_ktp'] = ($value !== null && $value !== '')
            ? SensitiveData::encrypt($value)
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
}
