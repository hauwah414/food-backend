<?php

Route::group(['prefix' => 'api/merchant','middleware' => ['log_activities','auth:api'], 'namespace' => 'Modules\Product\Http\Controllers'], function () {
    Route::get('product/category/list', 'ApiCategoryController@listCategoryCustomerApps');
});

Route::group(['prefix' => 'api/product','middleware' => ['log_activities','auth:api', 'scopes:apps'], 'namespace' => 'Modules\Product\Http\Controllers'], function () {
    /* product */
    Route::post('search', 'ApiCategoryController@search');
    Route::any('list', 'ApiProductController@listProductMerchant');
    Route::post('detail', 'ApiProductController@detail');
    Route::post('detail/review', 'ApiProductController@detailReview');
    Route::any('sync', 'ApiSyncProductController@sync');
    Route::get('next/{id}', 'ApiProductController@getNextID');
    Route::any('recommendation', 'ApiProductController@listProducRecommendation');
    Route::get('best-seller', 'ApiProductController@bestSeller');
    Route::get('newest', 'ApiProductController@newest');

    /* category */
    Route::group(['prefix' => 'category'], function () {

        Route::get('list', 'ApiCategoryController@listCategoryCustomerApps');
        Route::any('list/tree', 'ApiCategoryController@listCategoryTree');
    });
});


Route::group(['prefix' => 'api/v2/product', 'namespace' => 'Modules\Product\Http\Controllers'], function () {
    /* product */
    Route::any('list', 'ApiV2ProductController@listProductMerchant');
    Route::post('detail', 'ApiV2ProductController@detail');
    Route::any('recommendation', 'ApiV2ProductController@listProducRecommendation');
    Route::get('best-seller', 'ApiV2ProductController@bestSeller');
    Route::get('newest', 'ApiV2ProductController@newest');

});

Route::group(['prefix' => 'api/product','middleware' => ['log_activities','auth:api', 'scopes:be'], 'namespace' => 'Modules\Product\Http\Controllers'], function () {
    Route::any('spesial_price/create', 'ApiProductController@createSpesialPrice');
    Route::any('spesial_price', 'ApiProductController@indexSpesialPrice');
    Route::any('spesial_price/delete', 'ApiProductController@deleteSpesialPrice');
    
    //photo
     Route::group(['prefix' => 'multiple-photo'], function () {
        Route::any('create', 'ApiProductController@createPhoto');
        Route::any('/', 'ApiProductController@indexPhoto');
        Route::any('delete', 'ApiProductController@deletePhotos');
    });
    
    
    Route::any('customer', 'ApiProductController@customer');
    Route::any('merchant', 'ApiProductController@merchant');
    Route::any('be/list', 'ApiProductController@listProduct');
    Route::any('be/approved', 'ApiProductController@approved');
    Route::any('be/list/image', 'ApiProductController@listProductImage');
    Route::any('be/list/image/detail', 'ApiProductController@listProductImageDetail');
    Route::any('be/imageOverride', 'ApiProductController@imageOverride');
    Route::post('category/assign', 'ApiProductController@categoryAssign');
    Route::post('price/update', 'ApiProductController@priceUpdate');
    Route::post('detail/update', 'ApiProductController@updateProductDetail');
    Route::post('detail/update/price', 'ApiProductController@updatePriceDetail');
    Route::post('create', 'ApiProductController@create');
    Route::post('update', 'ApiProductController@update');
    Route::post('update/allow_sync', 'ApiProductController@updateAllowSync');
    Route::post('update/visibility/global', 'ApiProductController@updateVisibility');
    Route::post('update/visibility', 'ApiProductController@visibility');
    Route::post('position/assign', 'ApiProductController@positionProductAssign');//product position
    Route::post('delete', 'ApiProductController@delete');
    Route::post('import', 'ApiProductController@import');
    Route::get('list/price/{id_outlet}', 'ApiProductController@listProductPriceByOutlet');
    Route::get('list/product-detail/{id_outlet}', 'ApiProductController@listProductDetailByOutlet');
    Route::post('export', 'ApiProductController@export');
    Route::post('import', 'ApiProductController@import');
    Route::post('ajax-product-brand', 'ApiProductController@ajaxProductBrand');
    Route::post('product-brand', 'ApiProductController@getProductByBrand');
    Route::get('list/ajax', 'ApiProductController@listProductAjaxSimple');
    Route::post('recommendation/save', 'ApiProductController@productRecommendation');

    /* photo */
    Route::group(['prefix' => 'photo'], function () {
        Route::post('create', 'ApiProductController@uploadPhotoProduct');
        Route::post('update', 'ApiProductController@updatePhotoProduct');
        Route::post('createAjax', 'ApiProductController@uploadPhotoProductAjax');
        Route::post('overrideAjax', 'ApiProductController@overrideAjax');
        Route::post('delete', 'ApiProductController@deletePhotoProduct');
        Route::post('default', 'ApiProductController@photoDefault');
    });

    /* product modifier */
    Route::group(['prefix' => 'modifier'], function () {
        Route::any('/', 'ApiProductModifierController@index');
        Route::get('type', 'ApiProductModifierController@listType');
        Route::post('detail', 'ApiProductModifierController@show');
        Route::post('create', 'ApiProductModifierController@store');
        Route::post('update', 'ApiProductModifierController@update');
        Route::post('delete', 'ApiProductModifierController@destroy');
        Route::post('list-price', 'ApiProductModifierController@listPrice');
        Route::post('update-price', 'ApiProductModifierController@updatePrice');
        Route::post('list-detail', 'ApiProductModifierController@listDetail');
        Route::post('update-detail', 'ApiProductModifierController@updateDetail');
        Route::post('position-assign', 'ApiProductModifierController@positionAssign');
        Route::get('inventory-brand', 'ApiProductModifierController@inventoryBrand');
        Route::post('inventory-brand', 'ApiProductModifierController@inventoryBrandUpdate');
    });

    /* product modifier group */
    Route::group(['prefix' => 'modifier-group'], function () {
        Route::any('/', 'ApiProductModifierGroupController@index');
        Route::post('create', 'ApiProductModifierGroupController@store');
        Route::post('update', 'ApiProductModifierGroupController@update');
        Route::post('delete', 'ApiProductModifierGroupController@destroy');
        Route::post('list-price', 'ApiProductModifierGroupController@listPrice');
        Route::post('list-detail', 'ApiProductModifierGroupController@listDetail');
        Route::get('export', 'ApiProductModifierGroupController@export');
        Route::post('import', 'ApiProductModifierGroupController@import');
        Route::get('export-price', 'ApiProductModifierGroupController@exportPrice');
        Route::post('import-price', 'ApiProductModifierGroupController@importPrice');
        Route::post('position-assign', 'ApiProductModifierGroupController@positionAssign');
        Route::get('inventory-brand', 'ApiProductModifierGroupController@inventoryBrand');
        Route::post('inventory-brand', 'ApiProductModifierGroupController@inventoryBrandUpdate');
    });

    Route::group(['prefix' => 'category'], function () {
        Route::any('be/list', 'ApiCategoryController@listCategory');
        Route::post('position/assign', 'ApiCategoryController@positionCategoryAssign');
        Route::get('all', 'ApiCategoryController@getAllCategory');
        Route::post('create', 'ApiCategoryController@create');
        Route::post('update', 'ApiCategoryController@update');
        Route::post('edit', 'ApiCategoryController@edit');
        Route::post('delete', 'ApiCategoryController@delete');
    });

    Route::group(['prefix' => 'promo-category'], function () {
        Route::any('/', 'ApiPromoCategoryController@index')->middleware(['feature_control:236']);
        Route::post('assign', 'ApiPromoCategoryController@assign')->middleware(['feature_control:239']);
        Route::post('reorder', 'ApiPromoCategoryController@reorder')->middleware(['feature_control:239']);
        Route::post('create', 'ApiPromoCategoryController@store')->middleware(['feature_control:238']);
        Route::post('show', 'ApiPromoCategoryController@show')->middleware(['feature_control:237']);
        Route::post('update', 'ApiPromoCategoryController@update')->middleware(['feature_control:239']);
        Route::post('delete', 'ApiPromoCategoryController@destroy')->middleware(['feature_control:240']);
    });

    /* PRICES */
    Route::post('prices', 'ApiProductController@productPrices');
    Route::post('prices/all-product', 'ApiProductController@allProductPrices');
    Route::post('outlet-detail', 'ApiProductController@productDetail');
    Route::post('outlet-detail/all-product', 'ApiProductController@allProductDetail');

    /* tag */
    Route::group(['prefix' => 'tag'], function () {
        Route::any('list', 'ApiTagController@list');
        Route::post('create', 'ApiTagController@create');
        Route::post('update', 'ApiTagController@update');
        Route::post('delete', 'ApiTagController@delete');
    });

    /* product tag */
    Route::group(['prefix' => 'product-tag'], function () {
        Route::post('create', 'ApiTagController@createProductTag');
        Route::post('delete', 'ApiTagController@deleteProductTag');
    });
});
Route::group(['prefix' => 'api/search', 'namespace' => 'Modules\Product\Http\Controllers'], function () {
    /* product */
    Route::post('/', 'ApiSearchController@search');
    Route::post('/outlet', 'ApiSearchController@outlet');
});