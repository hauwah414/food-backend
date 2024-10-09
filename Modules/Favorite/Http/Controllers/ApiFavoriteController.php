<?php

namespace Modules\Favorite\Http\Controllers;

use App\Http\Models\ProductPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Favorite\Entities\Favorite;
use Modules\Favorite\Entities\FavoriteModifier;
use Modules\Favorite\Http\Requests\CreateRequest;
use App\Http\Models\Setting;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductModifierPrice;
use App\Http\Models\ProductModifierGlobalPrice;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use App\Lib\MyHelper;

class ApiFavoriteController extends Controller
{
    /**
     * Display a listing of favorite for admin panel
     * @return Response
     */
    public function index(Request $request)
    {
        $data = Favorite::with('modifiers', 'product', 'outlet');
        // need pagination?
        $id_favorite = $request->json('id_favorite');
        if ($id_favorite) {
            $data->where('id_favorite', $id_favorite);
        }
        if ($request->page && !$id_favorite) {
            $data = $data->paginate(10);
            if (!$data->total()) {
                $data = [];
            }
        } elseif ($id_favorite) {
            $data = $data->first();
        } else {
            $data = $data->get();
        }
        return MyHelper::checkGet($data, 'empty');
    }

    /**
     * Display a listing of favorite for mobile apps
     * @return Response
     */
    public function list(Request $request)
    {
        $user = $request->user();
        $id_favorite = $request->json('id_favorite');
        $latitude = $request->json('latitude');
        $longitude = $request->json('longitude');
        $nf = $request->json('number_format') ?: 'float';
        $favorite = Favorite::where('id_user', $user->id)->join('outlets', 'outlets.id_outlet', 'favorites.id_outlet')->where('outlets.outlet_status', 'Active');
        $select = ['id_favorite','favorites.id_outlet','outlet_different_price','favorites.id_product','favorites.id_product_variant_group','id_brand','id_user','notes'];
        $with = [
            'modifiers' => function ($query) {
                $query->select('product_modifiers.id_product_modifier', 'type', 'modifier_type', 'code', 'text', 'favorite_modifiers.qty');
            },
            'variants' => function ($query) {
                $query->select('product_variants.id_product_variant', 'product_variant_name');
            }
        ];
        // detail or list
        if (!$id_favorite) {
            if ($request->json('id_brand')) {
                $favorite->where('favorites.id_brand', $request->json('id_brand'));
            }

            if ($request->json('id_outlet')) {
                $favorite->where('favorites.id_outlet', $request->json('id_outlet'));
            }

            if ($request->json('topping') == 'used') {
                $favorite->whereRaw('favorites.id_favorite in (select fm.id_favorite from favorite_modifiers fm where fm.id_favorite = favorites.id_favorite)');
            } elseif ($request->json('topping') == 'unused') {
                $favorite->whereRaw('favorites.id_favorite not in (select fm.id_favorite from favorite_modifiers fm where fm.id_favorite = favorites.id_favorite)');
            }

            if ($request->json('key_free')) {
                $favorite->whereIn('favorites.id_favorite', function ($query) use ($request) {
                    $query->select('f.id_favorite')
                        ->from('favorites as f')
                        ->join('products', 'products.id_product', 'f.id_product')
                        ->join('outlets', 'outlets.id_outlet', 'f.id_outlet')
                        ->join('brands', 'brands.id_brand', 'f.id_brand')
                        ->where('products.product_name', 'LIKE', '%' . $request->json('key_free') . '%')
                        ->orWhere('outlets.outlet_name', 'LIKE', '%' . $request->json('key_free') . '%')
                        ->orWhere('brands.name_brand', 'LIKE', '%' . $request->json('key_free') . '%');
                });
            }

            if ($request->page) {
                $data = $favorite->select($select)->with($with)->paginate(10)->toArray();
                $datax = &$data['data'];
            } else {
                $data = $favorite->select($select)->with($with)->get()->toArray();
                $datax = &$data;
            }
            // filter has price only
            $datax = array_filter($datax, function ($x) {
                return $x['product']['price'];
            });
            if (count($datax) >= 1) {
                $datax = MyHelper::groupIt($datax, 'id_outlet', function ($key, &$val) use ($nf, $data, $request) {

                    $total_price = $val['product']['price'];
                    $val['product']['price'] = MyHelper::requestNumber($val['product']['price'], $nf);
                    $variants = [];
                    $val['extra_modifiers'] = [];
                    foreach ($val['modifiers'] as $keyx => &$modifier) {
                        if ($val['outlet_different_price']) {
                            $price = ProductModifierPrice::select('product_modifier_price')->where([
                                'id_product_modifier' => $modifier['id_product_modifier'],
                                'id_outlet' => $val['id_outlet']
                            ])->pluck('product_modifier_price')->first();
                        } else {
                            $price = ProductModifierGlobalPrice::select('product_modifier_price')->where('id_product_modifier', $modifier['id_product_modifier'])->pluck('product_modifier_price')->first();
                        }
                        $modifier['product_modifier_price'] = MyHelper::requestNumber($price, $nf);
                        $total_price += $price * $modifier['qty'];
                        if ($modifier['modifier_type'] == 'Modifier Group') {
                            $val['variants'][] = [
                                'id_product_variant' => $modifier['id_product_modifier'],
                                'product_variant_name' => $modifier['text']
                            ];
                            $val['extra_modifiers'][] = $modifier['id_product_modifier'];
                            unset($val['modifiers'][$keyx]);
                        }
                    }

                    if ($val['id_product_variant_group']) {
                        $outlet = Outlet::select('id_outlet', 'outlet_different_price')->where('id_outlet', $val['id_outlet'])->first();
                        $variant_tree = Product::getVariantTree($val['id_product'], $outlet);
                        if ($variant_tree['base_price'] ?? false) {
                            $total_price += $variant_tree['base_price'] - $val['product']['price'];
                            $variant_price = Product::getVariantPrice($val['id_product_variant_group'], $variant_tree['variants_tree'] ?? []);
                            $total_price += array_sum($variant_price ?: []);
                        }
                        $val['selected_variant'] = Product::getVariantParentId($val['id_product_variant_group'], $variant_tree['variants_tree'] ?? [], $val['extra_modifiers']);
                    } else {
                        $val['selected_variant'] = [];
                    }
                    $order = array_flip($val['selected_variant']);
                    usort($val['variants'], function ($a, $b) use ($order) {
                        return ($order[$a['id_product_variant']] ?? 999) <=> ($order[$b['id_product_variant']] ?? 999);
                    });
                    $val['product_price_total'] = $total_price;

                    if ($request->json('max_price') && $request->json('min_price')) {
                        if (
                            (int)$request->json('max_price') >= (int)$total_price &&
                            (int)$request->json('min_price') <= (int)$total_price
                        ) {
                            return $key;
                        } else {
                            return 'remove';
                        }
                    } else {
                        if (!empty($request->json('max_price')) && (int)$request->json('max_price') < (int)$total_price) {
                            return 'remove';
                        } elseif (!empty($request->json('min_price')) && (int)$request->json('min_price') > (int)$total_price) {
                            return 'remove';
                        } else {
                            return $key;
                        }
                    }
                }, function ($key, &$val) use ($latitude, $longitude) {
                    if ($key == "remove") {
                        return $key;
                    }

                    $outlet = Outlet::select('id_outlet', 'outlet_code', 'outlet_name', 'outlet_address', 'outlet_latitude', 'outlet_longitude')->with('today')->find($key)->toArray();
                    $status = app('Modules\Outlet\Http\Controllers\ApiOutletController')->checkOutletStatus($outlet);
                    $outlet['outlet_address'] = $outlet['outlet_address'] ?? '';
                    $outlet['status'] = $status;
                    if (!empty($latitude) && !empty($longitude)) {
                        $outlet['distance_raw'] = MyHelper::count_distance($latitude, $longitude, $outlet['outlet_latitude'], $outlet['outlet_longitude']);
                        $outlet['distance'] = MyHelper::count_distance($latitude, $longitude, $outlet['outlet_latitude'], $outlet['outlet_longitude'], 'K', true);
                    } else {
                        $outlet['distance_raw'] = null;
                        $outlet['distance'] = '';
                    }
                    $val = [
                        'outlet' => $outlet,
                        'favorites' => $val
                    ];
                    return $key;
                });
                unset($datax['remove']);
                $datax = array_values($datax);

                if (!empty($latitude) && !empty($longitude)) {
                    usort($datax, function (&$a, &$b) {
                        return $a['outlet']['distance_raw'] <=> $b['outlet']['distance_raw'];
                    });
                }
            } else {
                $data = [];
            }
        } else {
            $data = $favorite->select($select)->with($with)->where('id_favorite', $id_favorite)->first();
            if (!$data) {
                return MyHelper::checkGet($data);
            }
            $data = $data->toArray();
            $total_price = $data['product']['price'];
            $data['product']['price'] = MyHelper::requestNumber($data['product']['price'], $nf);
            foreach ($data['modifiers'] as &$modifier) {
                $price = ProductModifierPrice::select('product_modifier_price')->where([
                    'id_product_modifier' => $modifier['id_product_modifier'],
                    'id_outlet' => $data['id_outlet']
                ])->pluck('product_modifier_price')->first();
                $modifier['product_modifier_price'] = MyHelper::requestNumber($price, $nf);
                $total_price += $price * $modifier['qty'];
            }
            $data['product_price_total'] = $total_price;
        }
        return MyHelper::checkGet($data, 'empty');
    }

    /**
     * Add user favorite
     * @param Request $request
     * {
     *     'id_outlet'=>'',
     *     'id_product'=>'',
     *     'id_product_variant_group'=>'',
     *     'id_user'=>'',
     *     'notes'=>'',
     *     'product_qty'=>'',
     *     'modifiers'=>[id,id,id],
     *     'extra_modifiers'=>[id,id,id]
     * }
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        $id_user = $request->user()->id;
        $modifiers = array_merge($request->json('modifiers') ?: [], $request->json('extra_modifiers') ?: []);
        // check is already exist
        if ($request->json('id_product_variant_group')) {
            $variant_group_exists = ProductVariantGroup::where(['id_product_variant_group' => $request->json('id_product_variant_group'), 'id_product' => $request->json('id_product')])->exists();
            if (!$variant_group_exists) {
                return [
                    'status' => 'fail',
                    'messages' => ['Product variant not found']
                ];
            }
        }
        $data = Favorite::where([
            ['id_outlet',$request->json('id_outlet')],
            ['id_product',$request->json('id_product')],
            ['id_product_variant_group',$request->json('id_product_variant_group') ?: null],
            ['id_brand',$request->json('id_brand')],
            ['id_user',$id_user],
            ['notes',$request->json('notes') ?? '']
        ])->where(function ($query) use ($modifiers) {
            foreach ($modifiers as $modifier) {
                if (is_array($modifier)) {
                    $id_product_modifier = $modifier['id_product_modifier'];
                    $qty = $modifier['qty'] ?? 1;
                } else {
                    $id_product_modifier = $modifier;
                    $qty = 1;
                }
                $query->whereHas('favorite_modifiers', function ($query) use ($id_product_modifier, $qty) {
                    $query->where('favorite_modifiers.id_product_modifier', $id_product_modifier);
                    $query->where('favorite_modifiers.qty', $qty);
                });
            }
        })->having('modifiers_count', '=', count($modifiers))->withCount('modifiers')->first();
        $extra['message'] = Setting::select('value_text')->where('key', 'favorite_already_exists_message')->pluck('value_text')->first() ?: 'Favorite already exists';
        $new = 0;
        if (!$data) {
            $extra['message'] = Setting::select('value_text')->where('key', 'favorite_add_success_message')->pluck('value_text')->first() ?: 'Success add favorite';
            $new = 1;
            \DB::beginTransaction();
            // create favorite
            $insert_data = [
                'id_outlet' => $request->json('id_outlet'),
                'id_brand' => $request->json('id_brand'),
                'id_product' => $request->json('id_product'),
                'id_product_variant_group' => $request->json('id_product_variant_group') ?: null,
                'id_user' => $id_user,
                'notes' => $request->json('notes') ?: ''];

            $data = Favorite::create($insert_data);
            if ($data) {
                //insert modifier
                foreach ($modifiers as $modifier) {
                    if (is_array($modifier)) {
                        $id_product_modifier = $modifier['id_product_modifier'];
                        $qty = $modifier['qty'] ?? 1;
                    } else {
                        $id_product_modifier = $modifier;
                        $qty = 1;
                    }
                    $insert = FavoriteModifier::create([
                        'id_favorite' => $data->id_favorite,
                        'id_product_modifier' => $id_product_modifier,
                        'qty' => $qty
                    ]);
                    if (!$insert) {
                        \DB::rolBack();
                        return [
                            'status' => 'fail',
                            'messages' => ['Failed insert product modifier']
                        ];
                    }
                }
            } else {
                \DB::rollBack();
                return [
                    'status' => 'fail',
                    'messages' => ['Failed insert product modifier']
                ];
            }
            \DB::commit();
        }
        $data->load('modifiers');
        $data = $data->toArray();
        $data['create_new'] = $new;
        return MyHelper::checkCreate($data) + $extra;
    }

    public function storeV2(Request $request)
    {
        $user = $request->user();

        if (empty($request->json('id_product'))) {
            return [
                'status' => 'fail',
                'messages' => ['ID can not be empty']
            ];
        }

        $dtProduct = Product::join('product_detail', 'product_detail.id_product', 'products.id_product')
                    ->where('products.id_product', $request->json('id_product'))->first();

        if (empty($dtProduct)) {
            return [
                'status' => 'fail',
                'messages' => ['Product not found']
            ];
        }

        $checkExist = Favorite::where('id_product', $dtProduct['id_product'])->where('id_user', $user->id)->first();

        $data = $checkExist;
        if (empty($checkExist)) {
            $insert_data = [
                'id_outlet' => $dtProduct['id_outlet'],
                'id_product' => $dtProduct['id_product'],
                'id_user' => $user->id
            ];

            $data = Favorite::create($insert_data);
        }
        return response()->json(MyHelper::checkCreate($data));
    }

    /**
     * Remove favorite
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $delete = Favorite::where([
            ['id_product',$request->json('id_product')],
            ['id_user',$user->id]
        ])->delete();
        return MyHelper::checkDelete($delete);
    }

    public function listV2(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $list = Product::leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
            ->join('product_detail', 'product_detail.id_product', '=', 'products.id_product')
            ->join('favorites', 'favorites.id_product', '=', 'products.id_product')
            ->leftJoin('outlets', 'outlets.id_outlet', 'product_detail.id_outlet')
            ->where('product_global_price', '>', 0)
            ->where('product_visibility', 'Visible')
            ->where('product_detail_visibility', 'Visible')
            ->where('favorites.id_user', $user->id)
            ->select(
                'products.id_product',
                'products.product_name',
                'products.product_code',
                'products.product_description',
                'product_variant_status',
                'product_global_price as product_price',
                'product_detail_stock_status as stock_status',
                'product_detail.id_outlet',
                'need_recipe_status',
                'outlet_is_closed as outlet_holiday_status'
            )
            ->groupBy('products.id_product');

        if (!empty($post['pagination'])) {
            $list = $list->paginate($post['pagination_total_row'] ?? 10)->toArray();

            foreach ($list['data'] as $key => $product) {
                if ($product['product_variant_status']) {
                    $outlet = Outlet::where('id_outlet', $product['id_outlet'])->first();
                    $variantTree = Product::getVariantTree($product['id_product'], $outlet);
                    if (empty($variantTree['base_price'])) {
                        $list['data'][$key]['stock_status'] = 'Sold Out';
                    }
                    $list['data'][$key]['product_price'] = ($variantTree['base_price'] ?? false) ?: $product['product_price'];
                }

                unset($list['data'][$key]['id_outlet']);
                unset($list['data'][$key]['product_variant_status']);
                $list['data'][$key]['product_price'] = (int)$list['data'][$key]['product_price'];
                $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first();
                $list['data'][$key]['image'] = (!empty($image['product_photo']) ? config('url.storage_url_api') . $image['product_photo'] : config('url.storage_url_api') . 'img/default.jpg');
            }
            $list['data'] = array_values($list['data']);
        } else {
            $list = $list->get()->toArray();

            foreach ($list as $key => $product) {
                if ($product['product_variant_status']) {
                    $outlet = Outlet::where('id_outlet', $product['id_outlet'])->first();
                    $variantTree = Product::getVariantTree($product['id_product'], $outlet);
                    if (empty($variantTree['base_price'])) {
                        $list[$key]['stock_status'] = 'Sold Out';
                    }
                    $list[$key]['product_price'] = ($variantTree['base_price'] ?? false) ?: $product['product_price'];
                }

                unset($list[$key]['id_outlet']);
                unset($list[$key]['product_variant_status']);
                $list[$key]['product_price'] = (int)$list[$key]['product_price'];
                $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first();
                $list[$key]['image'] = (!empty($image['product_photo']) ? config('url.storage_url_api') . $image['product_photo'] : config('url.storage_url_api') . 'img/default.jpg');
            }
            $list = array_values($list);
        }

        return response()->json(MyHelper::checkGet($list));
    }
}
