<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:14 +0000.
 */

namespace Modules\Franchise\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AutocrmEmailLog
 *
 * @property int $id_autocrm_email_log
 * @property int $id_outlet
 * @property string $email_log_to
 * @property string $email_log_subject
 * @property string $email_log_message
 * @property int $email_log_is_read
 * @property int $email_log_is_clicked
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Http\Models\User $user
 *
 * @package App\Models
 */
class FranchiseEmailLog extends Model
{
    protected $primaryKey = 'id_franchise_email_log';

    protected $casts = [
        'id_outlet' => 'int',
        'email_log_is_read' => 'int',
        'email_log_is_clicked' => 'int'
    ];

    protected $fillable = [
        'id_outlet',
        'email_log_to',
        'email_log_subject',
        'email_log_message',
        'email_log_is_read',
        'email_log_is_clicked'
    ];

    public function outlet()
    {
        return $this->belongsTo(\App\Http\Models\Outlet::class, 'id_outlet');
    }
}
