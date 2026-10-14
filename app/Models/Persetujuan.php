<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// PRD F12 (P2, UI menyusul): persetujuan pemilik data, wajib sebelum data
// asli dimasukkan (UU PDP).
class Persetujuan extends Model
{
    protected $table = 'persetujuan';

    protected $fillable = ['id_anggota', 'nama_pemberi', 'tanggal', 'status'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Anggota::class, 'id_anggota');
    }
}
