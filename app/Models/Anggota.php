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
        'jenis_kelamin', 'status_kawin', 'id_agama', 'id_pendidikan',
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
            $anggota->kepala_lock = (int) $anggota->id_hubungan === RefHubungan::KEPALA
                ? $anggota->id_keluarga
                : null;
        });
    }

    public function setNikAttribute(string $value): void
    {
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
        $this->attributes['nama_ayah'] = $value === null ? null : SensitiveData::encrypt($value);
    }

    public function getNamaAyahAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setNamaIbuAttribute(?string $value): void
    {
        $this->attributes['nama_ibu'] = $value === null ? null : SensitiveData::encrypt($value);
    }

    public function getNamaIbuAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function setPenghasilanBulananAttribute($value): void
    {
        $this->attributes['penghasilan_bulanan'] = $value === null || $value === ''
            ? null
            : SensitiveData::encrypt((string) $value);
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
