<?php

namespace Modules\Product\Http\Controllers;

use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductCategory;
use App\Http\Models\ProductDiscount;
use App\Http\Models\ProductPhoto;
use App\Http\Models\ProductPrice;
use App\Http\Models\NewsProduct;
use App\Http\Models\Setting;
use Modules\Brand\Entities\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use Modules\Product\Entities\ProductDetail;
use Modules\Product\Entities\ProductGlobalPrice;
use Modules\Product\Entities\ProductSpecialPrice;
use Modules\ProductBundling\Entities\Bundling;
use Modules\ProductBundling\Entities\BundlingOutletGroup;
use Modules\ProductBundling\Entities\BundlingProduct;
use Modules\ProductBundling\Entities\BundlingToday;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use Modules\ProductVariant\Entities\ProductVariantGroupSpecialPrice;
use Validator;
use Hash;
use DB;
use Mail;
use Modules\Product\Http\Requests\category\CreateProduct;
use Modules\Product\Http\Requests\category\UpdateCategory;
use Modules\Product\Http\Requests\category\DeleteCategory;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Lib\PromoCampaignTools;
use App\Http\Models\ProductPriceUser;
use App\Http\Models\ProductServingMethod;
use App\Http\Models\ProductCustomGroup;

class ApiSearchController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->subscription_use     = "Modules\Subscription\Http\Controllers\ApiSubscriptionUse";
        $this->promo                   = "Modules\PromoCampaign\Http\Controllers\ApiPromo";
        $this->bundling                   = "Modules\ProductBundling\Http\Controllers\ApiBundlingController";
        $this->management_merchant = "Modules\Merchant\Http\Controllers\ApiMerchantManagementController";
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
        
    }

    public $saveImage = "img/product/category/";

    
    public function search(Request $request)
    {
        $post = $request->json()->all();
        $post['latitude'] = $post['latitude']??env('LATITUDE');
        $post['longitude'] = $post['longitude']??env('LONGITUDE');  
        if (!empty($post['id_outlet'])) {
            $idMerchant = Merchant::where('id_outlet', $post['id_outlet'])->first()['id_merchant'] ?? null;
            if (empty($idMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
            }
        }

        if (!empty($post['promo'])) {
            $availablePromo = $this->getProductFromPromo($post['promo']);
        }

        
        $list = Outlet::leftjoin('product_detail', 'product_detail.id_outlet','outlets.id_outlet')
            ->leftJoin('products', 'products.id_product', 'product_detail.id_product')
            ->leftJoin('cities', 'outlets.id_city', 'outlets.id_city')
            ->leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
            ->join('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
            ->where('outlet_status', 'Active')
            ->where('product_visibility', 'Visible')
            ->where('product_detail_visibility', 'Visible')
            ->groupBy('outlets.id_outlet');


        if (!empty($post['search_key'])) {
            if (strpos($post['search_key'], " ") !== false) {
                $list = $list->whereRaw('MATCH (product_name) AGAINST ("' . $post['search_key'] . '" IN BOOLEAN MODE)')->orwhereRaw('MATCH (outlet_name) AGAINST ("' . $post['search_key'] . '" IN BOOLEAN MODE)');
            } else {
                $list = $list->where('product_name', 'like', '%' . $post['search_key'] . '%')->orwhere('outlet_name', 'like', '%' . $post['search_key'] . '%');
            }
        }

        if (!empty($post['min_value'])) {
                $list = $list->where('product_global_price.product_global_price', '>=', $post['min_value'] );
           
        }
        if (!empty($post['max_value'])) {
                $list = $list->where('product_global_price.product_global_price', '<=', $post['max_value'] );
           
        }


        $defaultSelect = 1;
        if ($defaultSelect == 1) {
            $list = $list->select(
                'outlets.id_outlet',
                'outlets.outlet_code',
                'outlets.outlet_name',
                'outlet_is_closed as outlet_holiday_status',
                'outlets.id_outlet',
                'outlets.outlet_latitude',
                'outlets.outlet_longitude',
                'outlets.open',
                'outlets.close',
                'outlets.outlet_image_cover',
                'outlets.outlet_image_logo_portrait',
                'outlets.outlet_image_logo_landscape',
                DB::raw('(1.1515 * 1.609344 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(outlets.outlet_latitude))
                     * COS(RADIANS('.$post['latitude'].'))
                     * COS(RADIANS(outlets.outlet_longitude - '.$post['longitude'].'))
                     + SIN(RADIANS(outlets.outlet_latitude))
                     * SIN(RADIANS('.$post['latitude'].')))))) AS distance_in_km')
            ) ->orderBy('distance_in_km', 'asc');
        }

       $list = $list->paginate(10)->toArray();

        foreach ($list['data'] as $key => $product) {
            $pro = Product::leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
            ->join('product_detail', 'product_detail.id_product', '=', 'products.id_product')
            ->leftJoin('outlets', 'outlets.id_outlet', 'product_detail.id_outlet')
            ->leftJoin('cities', 'outlets.id_city', 'outlets.id_city')
            ->join('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
            ->where('outlet_status', 'Active')
            ->where('product_visibility', 'Visible')
            ->where('product_detail_visibility', 'Visible')
            ->where('outlets.id_outlet',$product['id_outlet'])
            ->groupBy('products.id_product');
            if (!empty($post['search_key'])) {
                if (strpos($post['search_key'], " ") !== false) {
                    $pro = $list->whereRaw('MATCH (product_name) AGAINST ("' . $post['search_key'] . '" IN BOOLEAN MODE)');
                } else {
                    $pro =$pro->where('product_name', 'like', '%' . $post['search_key'] . '%');
                }
            }
            if (!empty($post['min_value'])) {
                $pro =$pro->where('product_global_price.product_global_price', '>=', $post['min_value'] );
           
            }
            if (!empty($post['max_value'])) {
                    $pro = $pro->where('product_global_price.product_global_price', '<=', $post['max_value'] );

            }
            $pro =  $pro->select('products.id_product',
            'products.total_rating',
            DB::raw('
                    floor(products.total_rating) as rating
                '),
            'products.product_name',
            'products.product_code',
            'products.product_type',
            'products.product_description',
            'product_variant_status',
            'status_preorder',
            'value_preorder',
            'product_global_price as product_price')->get();
            foreach($pro as $p){
               $image = ProductPhoto::where('id_product', $p['id_product'])->orderBy('product_photo_order', 'asc')->first();
               $p['image'] = (!empty($image['product_photo']) ? config('url.storage_url_api') . $image['product_photo'] : config('url.storage_url_api') . 'img/default.jpg');
            }
            $list['data'][$key]['products'] = $pro;
            unset($list['data'][$key]['open']);
            unset($list['data'][$key]['close']);
            unset($list['data'][$key]['outlet_image_cover']);
            unset($list['data'][$key]['outlet_image_logo_portrait']);
            unset($list['data'][$key]['outlet_image_logo_landscape']);
            unset($list['data'][$key]['url_npwp_attachment']);
            unset($list['data'][$key]['url_nib_attachment']);
            unset($list['data'][$key]['url']);
        }
       
        return response()->json(MyHelper::checkGet($list));
    }
    public function outlet(Request $request)
    {
        $post = $request->json()->all();
        
        $list = Outlet::where('outlet_status', 'Active')
            ->groupBy('outlets.id_outlet')
            ->select('id_outlet','outlet_name','outlet_code','outlet_address');


        if (!empty($post['search_key'])) {
            
                $list = $list->where('outlet_name', 'like', $post['search_key']. '%' );
           
        }

        if (!empty($post['terbaru'])) {
                $list = $list->orderby('created_at','ASC');
           
        }
        if (!empty($post['abjad'])) {
                $list = $list->orderby('outlet_name','ASC');
           
        }

       $list = $list->paginate(10)->toArray();

        foreach ($list['data'] as $key => $product) {
            $bawah = 0;
            $atas = 0;
            $i = 0;
            $pro = Product::leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
            ->join('product_detail', 'product_detail.id_product', '=', 'products.id_product')
            ->join('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
            ->where('product_visibility', 'Visible')
            ->where('product_detail_visibility', 'Visible')
            ->where('id_outlet',$product['id_outlet'])
            ->groupBy('products.id_product');
        $pro =  $pro->select('products.id_product',
            'products.total_rating',
            DB::raw('
                    floor(products.total_rating) as rating
                '),
            'products.product_name',
            'products.product_code',
            'products.product_type',
            'product_global_price as product_price')->take(4)->get();
            foreach($pro as $p){
                if($p['product_type']=='product'){
                    if($i == 0){
                        $bawah = $p['product_price'];
                        $atas = $p['product_price'];
                    }
                    if($bawah > $p['product_price']){
                        $bawah = $p['product_price'];
                    }
                    if($atas < $p['product_price']){
                        $atas = $p['product_price'];
                    } 
                }
               
                
                $i++;
               $image = ProductPhoto::where('id_product', $p['id_product'])->orderBy('product_photo_order', 'asc')->first();
               $p['image'] = (!empty($image['product_photo']) ? config('url.storage_url_api') . $image['product_photo'] : config('url.storage_url_api') . 'img/default.jpg');
            }
            $list['data'][$key]['price'] = 'Rp '.number_format($bawah,0,",",".").' - '.'Rp '.number_format($atas,0,",",".");
            $list['data'][$key]['products'] = $pro;
            unset($list['data'][$key]['open']);
            unset($list['data'][$key]['close']);
            unset($list['data'][$key]['outlet_image_cover']);
            unset($list['data'][$key]['outlet_image_logo_portrait']);
            unset($list['data'][$key]['outlet_image_logo_landscape']);
            unset($list['data'][$key]['url_npwp_attachment']);
            unset($list['data'][$key]['url_nib_attachment']);
            unset($list['data'][$key]['url']);
        }
       
        return response()->json(MyHelper::checkGet($list));
    }

}
