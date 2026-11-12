<?php

namespace App\Models;

use App\Scopes\WilayahScope;
use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// PRD §8: identitas tersimpan terenkripsi; kepala_lock menegakkan tepat satu
// kepala keluarga di level database (UNIQUE, NULL untuk non-kepala).
class Anggota extends Model
{
    use SoftDeletes;

    protected $table = 'anggota';

    protected $fillable = [
        'profil',
        'id_keluarga', 'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'status_kawin', 'status_kependudukan', 'id_agama', 'id_pendidikan',
        'id_pekerjaan', 'id_hubungan', 'nama_ayah', 'nama_ibu', 'penghasilan_bulanan',
        'created_by',
    ];

    protected $hidden = ['profil', 'nik', 'nama_lengkap', 'nama_ayah', 'nama_ibu', 'penghasilan_bulanan'];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date', 'profil' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new WilayahScope);

        static::saving(function (Anggota $anggota) {
            if (empty($anggota->status_kependudukan)) {
                $anggota->status_kependudukan = 'menetap';
            }

            $anggota->kepala_lock = (int) $anggota->id_hubungan === RefHubungan::KEPALA
                ? $anggota->id_keluarga
                : null;

            if ($anggota->exists && $anggota->isDirty('profil')) {
                $rawOld = $anggota->getRawOriginal('profil');
                $oldProfil = null;
                if (is_string($rawOld) && $rawOld !== '') {
                    try {
                        $oldProfil = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($rawOld), true);
                    } catch (\Throwable) {
                        $oldProfil = null;
                    }
                }
                $cleanOld = is_array($oldProfil) ? array_filter($oldProfil, fn ($v) => $v !== null && $v !== '') : [];
                $cleanNew = is_array($anggota->profil) ? array_filter($anggota->profil, fn ($v) => $v !== null && $v !== '') : [];
                if ($cleanOld == $cleanNew) {
                    $anggota->setRawAttributes(array_merge($anggota->getAttributes(), ['profil' => $rawOld]));
                }
            }
        });
    }

    public function setNikAttribute(string $value): void
    {
        if ($this->exists && isset($this->attributes['nik']) && $this->nik === $value) {
            return;
        }

        $this->attributes['nik'] = SensitiveData::encrypt($value);
        $this->attributes['nik_hash'] = SensitiveData::hash(SensitiveData::digits($value));
    }

    public function getNikAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function getNikMaskedAttribute(): ?string
    {
        return $this->nik === null ? null : SensitiveData::mask($this->nik);
    }

    public function setNamaLengkapAttribute(string $value): void
    {
        if ($this->exists && isset($this->attributes['nama_lengkap']) && $this->nama_lengkap === $value) {
            return;
        }

        $this->attributes['nama_lengkap'] = SensitiveData::encrypt($value);
        // Indeks butir untuk pencarian nama (pendamping nik_hash).
        $this->attributes['nama_tokens'] = implode(' ', SensitiveData::nameTokens($value));
    }

    public function getNamaLengkapAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setNamaAyahAttribute(?string $value): void
    {
        $normalized = ($value !== null && $value !== '') ? $value : null;
        if ($this->exists && array_key_exists('nama_ayah', $this->attributes) && $this->nama_ayah === $normalized) {
            return;
        }

        $this->attributes['nama_ayah'] = $normalized === null ? null : SensitiveData::encrypt($normalized);
    }

    public function getNamaAyahAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setNamaIbuAttribute(?string $value): void
    {
        $normalized = ($value !== null && $value !== '') ? $value : null;
        if ($this->exists && array_key_exists('nama_ibu', $this->attributes) && $this->nama_ibu === $normalized) {
            return;
        }

        $this->attributes['nama_ibu'] = $normalized === null ? null : SensitiveData::encrypt($normalized);
    }

    public function getNamaIbuAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setPenghasilanBulananAttribute($value): void
    {
        $normalized = ($value === null || $value === '') ? null : (string) $value;
        if ($this->exists && array_key_exists('penghasilan_bulanan', $this->attributes) && $this->penghasilan_bulanan === $normalized) {
            return;
        }

        $this->attributes['penghasilan_bulanan'] = $normalized === null
            ? null
            : SensitiveData::encrypt($normalized);
    }

    public function getPenghasilanBulananAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public static function findByNik(string $nik): ?self
    {
        return static::withoutGlobalScopes()
            ->withTrashed()
            ->where('nik_hash', SensitiveData::hash(SensitiveData::digits($nik)))
            ->first();
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }

    public function agama(): BelongsTo
    {
        return $this->belongsTo(RefAgama::class, 'id_agama');
    }

    public function pendidikan(): BelongsTo
    {
        return $this->belongsTo(RefPendidikan::class, 'id_pendidikan');
    }

    public function pekerjaan(): BelongsTo
    {
        return $this->belongsTo(RefPekerjaan::class, 'id_pekerjaan');
    }

    public function hubungan(): BelongsTo
    {
        return $this->belongsTo(RefHubungan::class, 'id_hubungan');
    }
}
