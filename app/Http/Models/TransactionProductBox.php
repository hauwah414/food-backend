<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
class TransactionProductBox extends Model
{
    protected $primaryKey = 'id_transaction_product_boxs';
    protected $table = 'transaction_product_boxs';
    protected $fillable = [
        'id_transaction_product',
        'id_product',
        'name_product',
        'product_price',
        'base_price',
        'service',
        'tax',
        'cogs',
        'fee',
    ];

}
