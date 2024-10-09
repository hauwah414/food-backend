<?php

namespace Modules\Franchise\Entities;

use Illuminate\Database\Eloquent\Model;

class Disburse extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'disburse';
    protected $primaryKey = 'id_disburse';

    protected $fillable = [
        'disburse_nominal',
        'disburse_fee',
        'total_income_outlet',
        'id_bank_account',
        'disburse_status',
        'beneficiary_bank_name',
        'beneficiary_account_number',
        'beneficiary_name',
        'beneficiary_alias',
        'beneficiary_email',
        'request',
        'response',
        'error_message',
        'error_code',
        'notes',
        'reference_no',
        'old_reference_no',
        'total_outlet',
        'count_retry',
        'send_email_status'
    ];

    public function disburse_outlet()
    {
        return $this->hasMany(\Modules\Disburse\Entities\DisburseOutlet::class, 'id_disburse')
            ->join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet');
    }
}
