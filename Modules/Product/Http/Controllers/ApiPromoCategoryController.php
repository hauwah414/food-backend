<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Models\Product;
use App\Lib\MyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Product\Entities\ProductProductPromoCategory;
use Modules\Product\Entities\ProductPromoCategory;

class ApiPromoCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $data = ProductPromoCategory::select('id_product_promo_category', 'product_promo_category_name')
            ->withCount('products')
            ->orderBy('product_promo_category_order')
            ->orderBy('id_product_promo_category');
        if ($request->keyword) {
            $data->where('product_promo_category_name', 'like', "%{$request->keyword}%");
        }
        if ($request->page) {
            return MyHelper::checkGet($data->paginate());
        } else {
            return MyHelper::checkGet($data->get());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post   = $request->json()->all();
        $create = ProductPromoCategory::create($post);
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $use_product_variant = \App\Http\Models\Configs::where('id_config', 94)->pluck('is_active')->first();
        $data                = ProductPromoCategory::with(['products' => function ($query) use ($use_product_variant) {
            $query->select('product_product_promo_categories.id_product', 'product_code');
        }])->find($request->json('id_product_promo_category'));
        if (!$data) {
            return MyHelper::checkGet($data);
        }
        $result['info']     = $data;
        $result['products'] = Product::select('id_product', 'product_code', 'product_name')->get();
        return MyHelper::checkGet($result);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $post = $request->json()->all();
        $ppc  = ProductPromoCategory::find($request->json('id_product_promo_category'));
        if (!$ppc) {
            return MyHelper::checkGet([]);
        }
        $update = $ppc->update($post);
        return MyHelper::checkUpdate($update);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $delete = ProductPromoCategory::find($request->json('id_product_promo_category'))->delete();
        return MyHelper::checkDelete($delete);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function reorder(Request $request)
    {
        $id_product_promo_category = $request->id_product_promo_category ?: [];
        foreach ($id_product_promo_category as $key => $id) {
            ProductPromoCategory::where('id_product_promo_category', $id)->update(['product_promo_category_order' => $key]);
        }
        return [
            'status' => 'success',
        ];
    }

    public function assign(Request $request)
    {
        $post                      = $request->json()->all();
        $id_product_promo_category = $post['id_product_promo_category'];
        $up                        = 0;
        ProductProductPromoCategory::where('id_product_promo_category', $id_product_promo_category)->delete();
        foreach ($post['id_product'] as $key => $id_product) {
            $update = ProductProductPromoCategory::updateOrCreate(['id_product' => $id_product, 'id_product_promo_category' => $id_product_promo_category, 'position' => ($key + 1)]);
        }
        return MyHelper::checkUpdate(true);
    }
}
