<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductDetail extends Model
{
    protected $table = 'product_detail';
    public $primaryKey = 'id_product_detail';
    protected $fillable = [
        'id_product',
        'id_outlet',
        'product_detail_visibility',
        'product_detail_status',
        'product_detail_stock_status',
        'product_detail_stock_item',
        'max_order',
        'created_at',
        'updated_at'
    ];

    public function product()
    {
        return $this->belongsTo(App\Http\Models\Product::class, 'id_product');
    }

    public function outlet()
    {
        return $this->belongsTo(App\Http\Models\Outlet::class, 'id_outlet');
    }
}
