<?php

namespace Modules\Franchise\Entities;

use Illuminate\Database\Eloquent\Model;

class DailyReportTrx extends Model
{
    protected $connection = 'mysql3';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'daily_report_trx';

    protected $primaryKey = 'id_report_trx';

    /**
     * @var array
     */
    protected $fillable = [
        'id_outlet',
        'trx_type',
        'trx_date',
        'trx_count',
        'trx_tax',
        'trx_shipment',
        'trx_service',
        'trx_discount',
        'trx_subtotal',
        'trx_grand',
        'trx_shipment_go_send',
        'trx_net_sale',
        'trx_cashback_earned',
        'trx_point_earned',
        'trx_max',
        'trx_average',
        'cust_male',
        'cust_female',
        'cust_android',
        'cust_ios',
        'cust_telkomsel',
        'cust_xl',
        'cust_indosat',
        'cust_tri',
        'cust_axis',
        'cust_smart',
        'cust_teens',
        'cust_young_adult',
        'cust_adult',
        'cust_old',
        'trx_total_item',
        'first_trx_time',
        'last_trx_time'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
