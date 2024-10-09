<?php

namespace App\Http\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id_notification';
    protected $fillable = [
        'id_user',
        'type_notif',
        'title',
        'description',
        'status',
    ];
    public function customerable()
    {
        return $this->morphTo();
    }
    public static function triggerCreate($attributes, $title = null,$message = null,$user,$type = 'transaction')
    {
        \DB::beginTransaction();
         $order = new Notification();
         $order->customerable()->associate($attributes);
         $order->title = $title;
         $order->description = $message;
         $order->type_notif = $type;
         $order->id_user = $user;
         $order->save();
        \DB::commit();
        return true;
    }
}
