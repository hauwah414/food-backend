<?php

namespace Modules\Franchise\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Franchise\Entities\TransactionPickupGoSendUpdate;

class TransactionPickupGoSend extends Model
{
    protected $connection = 'mysql3';
    protected $primaryKey = 'id_transaction_pickup_go_send';

    protected $casts = [
        'id_transaction_pickup' => 'int'
    ];

    protected $fillable = [
        'id_transaction_pickup',
        'origin_name',
        'origin_phone',
        'origin_address',
        'origin_note',
        'origin_latitude',
        'origin_longitude',
        'destination_name',
        'destination_phone',
        'destination_address',
        'destination_address_name',
        'destination_short_address',
        'destination_note',
        'destination_latitude',
        'destination_longitude',
        'go_send_id',
        'go_order_no',
        'latest_status',
        'cancel_reason',
        'live_tracking_url',
        'driver_id',
        'driver_name',
        'driver_phone',
        'driver_photo',
        'vehicle_number',
        'receiver_name',
        'retry_count',
        'stop_booking_at',
        'created_at',
        'updated_at',
        'manual_order_no',
    ];

    public function transaction_pickup()
    {
        return $this->belongsTo(\App\Http\Models\TransactionPickup::class, 'id_transaction_pickup');
    }

    public function transaction_pickup_update()
    {
        return $this->hasMany(TransactionPickupGoSendUpdate::class, 'id_transaction_pickup_go_send')->orderBy('created_at', 'DESC')->orderBy('id_transaction_pickup_go_send_update', 'DESC');
    }
}
