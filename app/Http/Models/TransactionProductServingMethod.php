<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
class TransactionProductServingMethod extends Model
{
    protected $primaryKey = 'id_transaction_product_serving_method';
    protected $table = 'transaction_product_serving_methods';
    protected $fillable = [
        'id_transaction_product',
        'id_product_serving_method',
        'serving_name',
        'package',
        'unit_price',
    ];

}
