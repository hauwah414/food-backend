<?php

namespace Modules\Transaction\Entities;

use App\Http\Models\Configs;
use App\Http\Models\LogBalance;
use App\Http\Models\Transaction;
use App\Http\Models\User;
use Illuminate\Database\Eloquent\Model;

class TransactionShipmentTrackingUpdate extends Model
{
    protected $table = 'transaction_shipment_tracking_updates';

    protected $primaryKey = 'id_transaction_shipment_tracking_update';

    protected $fillable   = [
        'id_transaction',
        'shipment_order_id',
        'tracking_code',
        'tracking_description',
        'tracking_location',
        'tracking_date_time',
        'tracking_date_time_original',
        'tracking_timezone',
        'send_notification',
        'attachment'
    ];
    protected $appends  = ['url_attachment'];


    public function getUrlAttachmentAttribute()
    {
        if (empty($this->attachment)) {
            return null;
        } else {
            return config('url.storage_url_api') . $this->attachment;
        }
    }
}
