<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefAgama extends Model
{
    protected $table = 'ref_agama';

    public $timestamps = false;

    protected $fillable = ['nama'];
}
