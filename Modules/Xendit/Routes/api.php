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
Route::get('/testwa', 'XenditController@testwa');
Route::group(['prefix' => 'xendit'], function () {
    Route::post('notif', 'XenditController@notif')->name('notif_xendit');
    Route::any('virtual_account_paid_callback_url', 'XenditController@virtual_account_paid_callback_url')->name('virtual_account_paid_callback_url');
});

Route::group(['prefix' => 'xendit-account', 'middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:be']], function () {
    Route::get('/', 'XenditAccountController@index');
    Route::post('detail', 'XenditAccountController@show');
    Route::post('update', 'XenditAccountController@update');
});
