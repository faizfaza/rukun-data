<?php

namespace App\Models;

use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkonomiKeluarga extends Model
{
    protected $table = 'ekonomi_keluarga';

    protected $fillable = ['id_keluarga', 'tahun_data', 'kategori_ekonomi', 'created_by'];

    protected $hidden = ['kategori_ekonomi'];

    public function setKategoriEkonomiAttribute(string $value): void
    {
        $this->attributes['kategori_ekonomi'] = SensitiveData::encrypt($value);
    }

    public function getKategoriEkonomiAttribute(?string $value): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }
}
