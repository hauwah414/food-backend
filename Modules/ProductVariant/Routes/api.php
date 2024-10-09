<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([ 'middleware' => ['log_activities', 'auth:api','user_agent', 'scopes:be'], 'prefix' => 'product-variant'], function () {
    Route::any('/', ['middleware' => 'feature_control:278', 'uses' => 'ApiProductVariantController@index']);
    Route::post('position', ['middleware' => 'feature_control:281', 'uses' => 'ApiProductVariantController@position']);
    Route::post('store', ['middleware' => 'feature_control:279', 'uses' => 'ApiProductVariantController@store']);
    Route::post('edit', ['middleware' => 'feature_control:281', 'uses' => 'ApiProductVariantController@edit']);
    Route::post('update', ['middleware' => 'feature_control:281', 'uses' => 'ApiProductVariantController@update']);
    Route::post('delete', ['middleware' => 'feature_control:282', 'uses' => 'ApiProductVariantController@destroy']);
    Route::post('import', ['uses' => 'ApiProductVariantController@import']);
    Route::post('update/status', ['uses' => 'ApiProductVariantController@updateUseStatus']);
});

Route::group([ 'middleware' => ['log_activities', 'auth:api','user_agent', 'scopes:be'], 'prefix' => 'product-variant-group'], function () {
    Route::post('list', 'ApiProductVariantGroupController@listProductWithVariant');
    Route::get('ajax/{idProduct}', 'ApiProductVariantGroupController@productVariantGroupListAjax');
    Route::post('product-delete', 'ApiProductVariantGroupController@deleteVariantFromProduct');

    Route::any('/', ['uses' => 'ApiProductVariantGroupController@productVariantGroupListCreate']);
    Route::any('list-group', ['uses' => 'ApiProductVariantGroupController@productVariantGroupList']);
    Route::post('remove', ['uses' => 'ApiProductVariantGroupController@removeProductVariantGroup']);
    Route::post('delete', ['uses' => 'ApiProductVariantGroupController@deleteProductVariantGroup']);
    Route::any('list-price', ['uses' => 'ApiProductVariantGroupController@listPrice']);
    Route::any('update-price', ['uses' => 'ApiProductVariantGroupController@updatePrice']);
    Route::post('list-detail', 'ApiProductVariantGroupController@listDetail');
    Route::post('update-detail', 'ApiProductVariantGroupController@updateDetail');
    Route::post('export', 'ApiProductVariantGroupController@export');
    Route::post('import', 'ApiProductVariantGroupController@import');
    Route::post('export-price', 'ApiProductVariantGroupController@exportPrice');
    Route::post('import-price', ['uses' => 'ApiProductVariantGroupController@importPrice']);
});
