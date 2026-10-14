<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefPekerjaan extends Model
{
    protected $table = 'ref_pekerjaan';

    public $timestamps = false;

    protected $fillable = ['nama'];
}
