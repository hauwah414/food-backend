<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'departments';

    protected $primaryKey = 'id_department';

    protected $fillable   = [
        'nama_department',
    ];
}
