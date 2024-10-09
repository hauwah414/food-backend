<?php

namespace Modules\Shift\Entities;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'id_outlet',
        'id_user_outletapp',
        'open_time',
        'close_time',
        'cash_start',
        'cash_end',
        'cash_difference',
    ];

    public function outlet()
    {
        return $this->belongsTo('App\Http\Models\Outlet', 'id_outlet');
    }

    public function user_outletapp()
    {
        return $this->belongsTo('App\Http\Models\UserOutletApp', 'id_user_outletapp');
    }
}
