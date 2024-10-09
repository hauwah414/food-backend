<?php

namespace Modules\Franchise\Entities;

use App\Lib\MyHelper;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $connection = 'mysql3';
    protected $primaryKey = 'id_product_variant';
    protected $hidden = ['pivot'];
    protected $fillable = [
        'product_variant_name',
        'id_parent',
        'product_variant_order',
        'product_variant_visibility'
    ];

    public $childs = [];

    public function product_variant_parent()
    {
        return $this->belongsTo(ProductVariant::class, 'id_parent', 'id_product_variant');
    }

    public function product_variant_child()
    {
        return $this->hasMany(ProductVariant::class, 'id_parent', 'id_product_variant');
    }

    public function parent()
    {
        return $this->belongsTo(ProductVariant::class, 'id_parent');
    }

    public function getIsCorAttribute()
    {
        return $this->is_cor;
    }

    public function getChildsAttribute()
    {
        return $this->childs;
    }

    public static function getVariantTree($variants = [])
    {
        $to_select = ['id_product_variant', 'product_variant_name', 'id_parent', 'product_variant_order'];
        $variants_raw = self::select($to_select)->where('product_variant_visibility', 'Visible')->orderByRaw('CASE WHEN product_variant_order is not null then 1 else 2 end')->orderBy('product_variant_order');
        if ($variants) {
            $variants_raw->whereIn('id_product_variant', $variants)->orWhereNull('id_parent');
        }
        $variants_raw = $variants_raw->get();
        $variants = [];
        $variants_raw->each(function ($each) use (&$variants) {
            $variants[$each->id_product_variant] = $each;
        });
        if (!$variants) {
            return [];
        }
        // pc = parent child
        $pc = [];
        foreach ($variants as $key => $variant) {
            $id_parent = $variant->id_parent ?: '_root';
            if (!isset($pc[$id_parent]['product_variant_name'])) {
                if ($id_parent == '_root') {
                    $parent = [
                        'id_product_variant' => 0,
                        'product_variant_name' => 'Root',
                        'product_variant_order' => 0,
                    ];
                } else {
                    $parent = $variants[$id_parent] ?? null;
                    if (!$parent) {
                        $parent = ProductVariant::select($to_select)->where('id_product_variant', $id_parent)->first();
                        self::addToParent($parent, $pc, $variants);
                    }
                    $parent = $parent->toArray();
                }
                $pc[$id_parent] = $parent;
            }
            $pc[$id_parent]['childs'][] = $variant;
        }

        $starter = array_shift($pc['_root']['childs']);
        while ($pc['_root']['childs'] && !isset($pc[$starter['id_product_variant']])) {
            $starter = array_shift($pc['_root']['childs']);
        }

        if (!($starter) || !isset($pc[$starter['id_product_variant']])) {
            return [];
        }


        $starter = $starter->toArray();
        $starter['childs'] = $pc[$starter['id_product_variant']]['childs'];

        foreach ($starter['childs'] as &$child) {
            $child->variant = self::getVariantChildren($child, $pc);
            $child = $child->toArray();
        }

        return $starter;
    }

    protected static function getVariantChildren($variant, $variants)
    {
        if ($childs = $variants[$variant['id_product_variant']]['childs'] ?? false) {
            foreach ($childs as $key => $child) {
                $child->variant = self::getVariantChildren($child, $variants);
                $childs[$key] = $child->toArray();
            }
            $variant_res = $variant->toArray();
            $variant_res['childs'] = $childs;
            return $variant_res;
        } elseif ($variants['_root']['childs']) {
            $starter = array_shift($variants['_root']['childs']);
            while ($variants['_root']['childs'] && !isset($variants[$starter['id_product_variant']])) {
                $starter = array_shift($variants['_root']['childs']);
            }
            foreach ($variants[$starter['id_product_variant']]['childs'] ?? [] as $key => $child) {
                $child->variant = self::getVariantChildren($child, $variants);
                $variants[$starter['id_product_variant']]['childs'][$key] = $child->toArray();
            }
            return $variants[$starter['id_product_variant']] ?? null;
        }
        return null;
    }

    protected static function addToParent($variant, &$pc, $variants)
    {
        if (!isset($pc[$variant['id_parent']])) {
            if (isset($variants[$variant['id_parent']])) {
                $pc[$variant['id_parent']] = $variants[$variant['id_parent']]->toArray();
            } else {
                $pc[$variant['id_parent']] = ProductVariant::where('id_parent', $variant['id_parent'])->first()->toArray();
            }
            $pc[$variant['id_parent']]['childs'] = [];
        }
        $pc[$variant['id_parent']]['childs'][] = $variant;
        usort($pc[$variant['id_parent']]['childs'], function ($a, $b) {
            return $a['product_variant_order'] <=> $b['product_variant_order'];
        });
    }
}
