<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalMonthlyReportTrxMenu extends Model
{
    protected $connection = 'mysql';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'global_monthly_report_trx_menu';

    protected $primaryKey = 'id_global_monthly_report_trx_menu';

    /**
     * @var array
     */
    protected $fillable = [
        'trx_month',
        'trx_year',
        'id_product',
        'type',
        'total_rec',
        'total_qty',
        'total_nominal',
        'total_product_discount',
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
        'product_name'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product')->select('id_product', 'product_code', 'product_name');
    }
}
