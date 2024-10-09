<?php

namespace Modules\Brand\Http\Controllers;

use App\Http\Models\Deal;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use App\Lib\MyHelper;
use DB;
use Illuminate\Support\Facades\Route;

class ApiBrandController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $post = $request->json()->all();

        $brand = Brand::orderByRaw('CASE WHEN order_brand = 0 THEN 1 ELSE 0 END')->orderBy('order_brand');

        $brand = $brand->get()->toArray();

        return response()->json(MyHelper::checkGet($brand));
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post = $request->json()->all();

        if (isset($post['logo_brand'])) {
            $upload = MyHelper::uploadPhotoStrict($post['logo_brand'], $path = 'img/brand/logo/', 200, 200);

            if ($upload['status'] == "success") {
                $post['logo_brand'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }

        if (isset($post['image_brand'])) {
            $upload = MyHelper::uploadPhoto($post['image_brand'], $path = 'img/brand/image/');
            if ($upload['status'] == "success") {
                $post['image_brand'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }

        DB::beginTransaction();
        if (isset($post['id_brand'])) {
            $request->validate([
                'name_brand'    => 'required'
            ]);

            if (isset($post['code_brand'])) {
                unset($post['code_brand']);
            }

            try {
                $defaultBrand = $post['default_brand_status'] ?? null;
                unset($post['default_brand_status']);

                Brand::where('id_brand', $post['id_brand'])->update($post);

                $checkSetting = Setting::where('key', 'default_brand')->first()['value'] ?? null;
                if (!empty($defaultBrand)) {
                    $default = $post['id_brand'];
                    Setting::updateOrCreate(['key' => 'default_brand'], ['value' => $default]);
                } elseif (empty($defaultBrand) && $checkSetting == $post['id_brand']) {
                    Setting::updateOrCreate(['key' => 'default_brand'], ['value' => null]);
                }
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Update Brand Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
            DB::commit();
            return response()->json(['status'  => 'success', 'result' => ['id_brand' => $post['id_brand']]]);
        } else {
            $request->validate([
                'name_brand'    => 'required',
                'code_brand'    => 'required'
            ]);

            $checkCode = Brand::where('code_brand', $post['code_brand'])->first();
            if (!empty($checkCode)) {
                $result = [
                    'status'  => 'fail',
                    'messages' => ['Failed create brand. Code already use.']
                ];
                DB::rollBack();
                return response()->json($result);
            }

            try {
                $defaultBrand = $post['default_brand_status'] ?? null;
                unset($post['default_brand_status']);

                $save = Brand::create($post);

                if (!empty($defaultBrand)) {
                    Setting::updateOrCreate(['key' => 'default_brand'], ['value' => $save['id_brand']]);
                }
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Brand Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
            DB::commit();
            return response()->json(['status'  => 'success', 'result' => ['id_brand' => $save->id_brand, 'created_at' => $save->created_at]]);
        }
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show(Request $request)
    {
        $post = $request->json()->all();

        $getBrand = Brand::with([
            'brand_outlet.outlets',
            'brand_product' => function ($q) {
                $q->whereHas('products');
            },
            'brand_product.products',
        ])
        ->where('id_brand', $post['id_brand'])->get()->first();

        $getBrand['brand_deal'] = Deal::where('id_brand', $post['id_brand'])->get()->toArray();
        $default = Setting::where('key', 'default_brand')->first()['value'] ?? null;
        $getBrand['default_brand_status'] = ($default == $post['id_brand'] ? 1 : 0);
        return response()->json(['status'  => 'success', 'result' => $getBrand]);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $product_exists = Product::join('brand_product', 'products.id_product', 'brand_product.id_product')->where('id_brand', $request->json('id_brand'))->exists();
            if ($product_exists) {
                return response()->json([
                    'status' => 'fail',
                    'messages' => ['Cannot delete the brand that has been assigned to the product']
                ]);
            }
            $delete = Brand::where('id_brand', $request->json('id_brand'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['outlet has been used.']
            ]);
        }
    }
    public function destroyOutlet(Request $request)
    {
        try {
            $delete = BrandOutlet::where('id_brand_outlet', $request->json('id_brand_outlet'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['outlet has been used.']
            ]);
        }
    }
    public function destroyProduct(Request $request)
    {
        try {
            $delete = BrandProduct::where('id_brand_product', $request->json('id_brand_product'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['outlet has been used.']
            ]);
        }
    }
    public function destroyDeals(Request $request)
    {
        try {
            $delete = Deal::where('id_deals', $request->json('id_deals'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['outlet has been used.']
            ]);
        }
    }

    public function listBrand(Request $request)
    {
        $currentPath = Route::getFacadeRoot()->current()->uri();
        $post = $request->json()->all();
        $brand = Brand::select('id_brand', 'brand_active', 'name_brand', 'logo_brand', 'image_brand')->orderByRaw('CASE WHEN order_brand = 0 THEN 1 ELSE 0 END')->orderBy('order_brand');

        if (strpos($currentPath, 'be') === false) {
            $brand = $brand->where('brand_visibility', 1);
        }

        if ($request->json('active')) {
            $brand->where('brand_active', 1);
        }
        if (isset($_GET['page'])) {
            $brand = $brand->paginate(10)->toArray();
            if (!$brand) {
                return response()->json(['status'  => 'fail', 'messages' => ['empty!']]);
            }
            $data['data']           = $brand['data'];
            $data['next_page_url']  = $brand['next_page_url'];
            $loop=&$data['data'];
        } else {
            $brand = $brand->get()->toArray();
            if (!$brand) {
                return response()->json(['status'  => 'fail', 'messages' => ['empty!']]);
            }
            $data = $brand;
            $loop=&$data;
        }
        //get default image
        if ($inactive_image = Setting::where('key', 'inactive_image_brand')->pluck('value')->first()) {
            $inactive_image = config('url.storage_url_api') . $inactive_image;
        } else {
            $inactive_image = '';
        }
        //replace if inactive
        foreach ($loop as &$bran) {
            if (!$bran['brand_active']) {
                $bran['image_brand'] = $inactive_image;
            }
        }
        //return
        return response()->json(['status'  => 'success', 'result' => $data]);
    }

    public function outletList(Request $request)
    {
        $post = $request->json()->all();

        $listOutlet = Outlet::whereNotIn('id_outlet', BrandOutlet::where('id_brand', $post['id_brand'])->get()->pluck('id_outlet'))->get();

        return response()->json($listOutlet);
    }

    public function productList(Request $request)
    {
        $post = $request->json()->all();

        $listProduct = Product::whereNotIn('id_product', BrandProduct::where('id_brand', $post['id_brand'])->get()->pluck('id_product'))->get();

        return response()->json($listProduct);
    }

    public function outletStore(Request $request)
    {
        $post = $request->json()->all();

        try {
            $create = BrandOutlet::insert($post);
            return response()->json(MyHelper::checkDelete($create));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['outlet has been used.']
            ]);
        }
    }

    public function productStore(Request $request)
    {
        $post = $request->json()->all();
        $post = array_map(function ($var) {
            $id_product_category = BrandProduct::select('id_product_category')->where('id_product', $var['id_product'])->orderBy('id_product_category')->pluck('id_product_category')->first();
            $var['id_product_category'] = $id_product_category;
            return $var;
        }, $post);
        try {
            $create = BrandProduct::insert($post);
            return response()->json(MyHelper::checkDelete($create));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['product has been used.']
            ]);
        }
    }

    public function reOrder(Request $request)
    {
        if (($order = $request->post('order')) && is_array($order)) {
            \DB::beginTransaction();
            $start = $request->post('data_start') ?? 0;
            foreach ($order as $id) {
                $start++;
                $update = ['order_brand' => $start];
                $save = Brand::find($id)->update($update);
                if (!$save) {
                    \DB::rollBack();
                    return [
                        'status' => 'fail',
                        'messages' => ['Update brand fail']
                    ];
                }
            }
            \DB::commit();
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'fail',
            'messages' => ['No brand updated']
        ];
    }
    public function inactiveImage(Request $request)
    {
        $post = $request->json()->all();

        if (isset($post['logo_brand'])) {
            $upload = MyHelper::uploadPhoto($post['logo_brand'], $path = 'img/brand/', null, 'default_logo');
            if ($upload['status'] == "success") {
                $logo_brand = $upload['path'];
                Setting::updateOrCreate(['key' => 'inactive_logo_brand'], ['value' => $logo_brand]);
            } else {
                $messages[] = 'fail upload logo';
            }
        }

        if (isset($post['image_brand'])) {
            $upload = MyHelper::uploadPhoto($post['image_brand'], $path = 'img/brand/image/', null, 'default_image');
            if ($upload['status'] == "success") {
                $image_brand = $upload['path'];
                Setting::updateOrCreate(['key' => 'inactive_image_brand'], ['value' => $image_brand]);
            } else {
                $messages[] = 'fail upload image';
            }
        }

        if ($messages ?? false) {
            return [
                'status' => 'fail',
                'messages' => $messages
            ];
        }
        return ['status' => 'success'];
    }

    public function switchStatus(Request $request)
    {
        $save = Brand::where('id_brand', $request->json('id_brand'))->update(['brand_active' => $request->json('brand_active') == "true" ? 1 : 0]);
        return MyHelper::checkUpdate($save);
    }

    public function switchVisibility(Request $request)
    {
        $save = Brand::where('id_brand', $request->json('id_brand'))->update(['brand_visibility' => $request->json('brand_visibility') == "true" ? 1 : 0]);
        return MyHelper::checkUpdate($save);
    }

    public function defaultBrand()
    {
        $value =  Setting::where('key', 'default_brand')->first()['value'] ?? null;
        return MyHelper::checkGet($value);
    }
}
