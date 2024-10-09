<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class SumberDana extends Model
{
    protected $primaryKey = 'id_sumber_dana';
    protected $table = 'sumber_danas';
    protected $fillable = [
        'sumber_dana',
    ];
}
