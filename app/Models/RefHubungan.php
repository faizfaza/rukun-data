<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Id 1 ditetapkan sebagai kepala keluarga (diisi seeder) dan dipakai
// aturan satu-kepala di model Anggota.
class RefHubungan extends Model
{
    public const KEPALA = 1;

    protected $table = 'ref_hubungan';

    public $timestamps = false;

    protected $fillable = ['nama'];
}
