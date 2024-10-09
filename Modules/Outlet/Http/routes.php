<?php

Route::group(['prefix' => 'api/outlet', 'middleware' => ['log_activities', 'auth:api', 'user_agent', 'scopes:apps'], 'namespace' => 'Modules\Outlet\Http\Controllers'], function () {

    Route::any('product', 'ApiOutletController@listProductMerchant');
    Route::any('list', 'ApiOutletController@outletMerchantList');
    Route::any('nearby', 'ApiOutletController@outletMerchantNearby');
    Route::any('list/simple', 'ApiOutletController@listOutletSimple');
    Route::any('list/ordernow', 'ApiOutletController@listOutletOrderNow');
    Route::any('list/gofood', 'ApiOutletGofoodController@listOutletGofood');
    Route::any('filter', 'ApiOutletController@filter');
    Route::any('filter/gofood', 'ApiOutletController@filter');
/* New API for filter outlet + product */
    Route::any('filter_product_outlet', 'ApiOutletController@filterProductOutlet');
/*WEBVIEW*/
    Route::any('webview/{id}', 'ApiOutletWebview@detailWebview');
    Route::any('detail/mobile', 'ApiOutletWebview@detailOutlet');
    Route::any('webview/gofood/list', 'ApiOutletWebview@listOutletGofood');
    Route::any('webview/gofood/list/v2', 'ApiOutletWebview@listOutletGofood');
    Route::get('city', 'ApiOutletController@cityOutlet');
// Route::any('filter', 'ApiOutletController@filter');
    Route::any('nearme/geolocation', 'ApiOutletController@nearMeGeolocation');
    Route::any('filter/geolocation', 'ApiOutletController@filterGeolocation');
    Route::any('sync', 'ApiSyncOutletController@sync');
//SYNC

    Route::post('detail', 'ApiOutletController@detailOutletMerchant');
    Route::get('store-page', 'ApiOutletController@outletMerchantStorePage');
    Route::any('featured-promo-campaign', 'ApiOutletController@featuredPromoCampaign');
});
Route::group(['prefix' => 'api/v2/outlet',  'namespace' => 'Modules\Outlet\Http\Controllers'], function () {
    Route::any('product', 'ApiV2OutletController@listProductMerchant');
    Route::any('list', 'ApiV2OutletController@outletMerchantList');
    Route::any('nearby', 'ApiV2OutletController@outletMerchantNearby');
    Route::post('detail', 'ApiV2OutletController@detailOutletMerchant');
});


Route::group(['prefix' => 'api/outlet', 'middleware' => ['log_activities', 'auth:api','user_agent', 'scopes:be'], 'namespace' => 'Modules\Outlet\Http\Controllers'], function () {

    Route::any('data-legalitas', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@dataLegalitas']);
    Route::any('be/list', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@listOutlet'])->name('outlet_be');
    Route::any('be/detail', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@listOutlet'])->name('outlet_be');
    Route::any('be/bank', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@listOutletBank']);
    Route::any('be/list/simple', 'ApiOutletController@listOutletSimple');
    Route::any('be/list/product-detail', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@listOutletProductDetail']);
    Route::any('be/list/product-special-price', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@listOutletProductSpecialPrice']);
    Route::any('be/filter', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@filter']);
    Route::any('list/code', ['middleware' => 'feature_control:24', 'uses' => 'ApiOutletController@getAllCodeOutlet']);
    Route::any('ajax_handler', 'ApiOutletController@ajaxHandler');
    Route::post('different_price', 'ApiOutletController@differentPrice');
    Route::post('different_price/update', 'ApiOutletController@updateDifferentPrice');
/* photo */
    Route::group(['prefix' => 'photo'], function () {

        Route::post('create', ['middleware' => 'feature_control:29', 'uses' => 'ApiOutletController@upload']);
        Route::post('update', ['middleware' => 'feature_control:30', 'uses' => 'ApiOutletController@updatePhoto']);
        Route::post('delete', ['middleware' => 'feature_control:30', 'uses' => 'ApiOutletController@deleteUpload']);
    });
    Route::group(['prefix' => 'delivery'], function () {
        Route::post('/', ['uses' => 'ApiOutletController@indexDelivery']);
        Route::post('create', ['uses' => 'ApiOutletController@createDelivery']);
        Route::post('delete', ['uses' => 'ApiOutletController@deleteDelivery']);
    });
/* holiday */
    Route::group(['prefix' => 'holiday'], function () {

        Route::any('list', ['middleware' => 'feature_control:34', 'uses' => 'ApiOutletController@listHoliday']);
        Route::post('create', ['middleware' => 'feature_control:36', 'uses' => 'ApiOutletController@createHoliday']);
        Route::post('update', ['middleware' => 'feature_control:37', 'uses' => 'ApiOutletController@updateHoliday']);
        Route::post('delete', ['middleware' => 'feature_control:38', 'uses' => 'ApiOutletController@deleteHoliday']);
    });
// admin outlet
    Route::group(['prefix' => 'admin'], function () {

        Route::post('create', ['middleware' => 'feature_control:40', 'uses' => 'ApiOutletController@createAdminOutlet']);
        Route::post('detail', ['middleware' => 'feature_control:39', 'uses' => 'ApiOutletController@detailAdminOutlet']);
        Route::post('update', ['middleware' => 'feature_control:41', 'uses' => 'ApiOutletController@updateAdminOutlet']);
        Route::post('delete', ['middleware' => 'feature_control:42', 'uses' => 'ApiOutletController@deleteAdminOutlet']);
    });
    Route::post('import-brand', 'ApiOutletController@importBrand');
    Route::post('import-delivery', 'ApiOutletController@importDelivery');
    Route::any('delivery-outlet-ajax', 'ApiOutletController@deliveryOutletAjax');
    Route::post('delivery-outlet/bycode', 'ApiOutletController@deliveryOutletByCode');
    Route::post('delivery-outlet/update', 'ApiOutletController@deliveryOutletUpdate');
    Route::post('delivery-outlet/all/update', 'ApiOutletController@deliveryOutletAllUpdate');
    Route::get('list-delivery/count-outlet', 'ApiOutletController@listDeliveryWithCountOutlet');
    Route::post('create', ['middleware' => 'feature_control:26', 'uses' => 'ApiOutletController@create']);
    Route::post('update', ['middleware' => 'feature_control:27', 'uses' => 'ApiOutletController@update']);
    Route::post('batch-update', 'ApiOutletController@batchUpdate');
    Route::post('update/status', 'ApiOutletController@updateStatus');
    Route::post('update/pin', 'ApiOutletController@updatePin');
    Route::post('delete', 'ApiOutletController@delete');
    Route::post('export', 'ApiOutletController@export');
    Route::post('export-city', 'ApiOutletController@exportCity');
    Route::post('import', 'ApiOutletController@import');
    Route::post('max-order', 'ApiOutletController@listMaxOrder');
    Route::post('max-order/update', 'ApiOutletController@updateMaxOrder');
    Route::any('schedule/save', 'ApiOutletController@scheduleSave');
    Route::get('export-pin', ['middleware' => 'feature_control:261', 'uses' => 'ApiOutletController@exportPin']);
    Route::get('send-pin', ['middleware' => 'feature_control:261', 'uses' => 'ApiOutletController@sendPin']);
/*user franchise*/
    Route::any('list/user-franchise', 'ApiOutletController@listUserFranchise');
    Route::any('detail/user-franchise', 'ApiOutletController@detailUserFranchise');
    Route::post('user-franchise/set-password-default', 'ApiOutletController@setPasswordDefaultUserFranchise');
    Route::post('schedule/restore', 'ApiOutletController@restoreSchedule');
/*group filter*/
    Route::group(['prefix' => 'group-filter'], function () {

        Route::post('store', ['middleware' => 'feature_control:296', 'uses' => 'ApiOutletGroupFilterController@store']);
        Route::get('/', ['middleware' => 'feature_control:294,297,298', 'uses' => 'ApiOutletGroupFilterController@list']);
        Route::post('detail', ['middleware' => 'feature_control:295,297', 'uses' => 'ApiOutletGroupFilterController@detail']);
        Route::post('update', ['middleware' => 'feature_control:297', 'uses' => 'ApiOutletGroupFilterController@update']);
        Route::post('delete', ['middleware' => 'feature_control:297', 'uses' => 'ApiOutletGroupFilterController@destroy']);
    });
});
