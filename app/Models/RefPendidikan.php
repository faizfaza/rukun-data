<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefPendidikan extends Model
{
    protected $table = 'ref_pendidikan';

    public $timestamps = false;

    protected $fillable = ['nama'];
}
