<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsetKeluarga extends Model
{
    protected $table = 'aset_keluarga';

    protected $fillable = ['id_keluarga', 'jenis_aset', 'keterangan', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (AsetKeluarga $model): void {
            if (! $model->created_by && auth()->id()) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
