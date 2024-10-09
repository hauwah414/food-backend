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

Route::group(['middleware' => ['auth:api', 'user_agent', 'scopes:be'], 'prefix' => 'achievement'], function () {
    Route::any('/', 'ApiAchievement@index');
    Route::any('category', 'ApiAchievement@category');
    Route::any('create', 'ApiAchievement@create');
    Route::any('update', 'ApiAchievement@updateAch');
    Route::any('detail', 'ApiAchievement@show');
    Route::any('detailAjax', 'ApiAchievement@detailAjax');
    Route::any('detail/update', 'ApiAchievement@update');
    Route::any('destroy', 'ApiAchievement@destroy');
    Route::group(['prefix' => 'report'], function () {
        /*Report Achievement*/
        Route::any('/', 'ApiReportAchievement@reportAchievement');
        Route::any('detail', 'ApiReportAchievement@reportDetailAchievement');
        Route::any('list/user-achievement', 'ApiReportAchievement@listUserAchivement');

        /*Report Achievement User*/
        Route::any('user-achievement', 'ApiReportAchievement@reportUser');
        Route::any('user-achievement/detail', 'ApiReportAchievement@reportDetailUser');
        Route::any('user-achievement/detail-badge', 'ApiReportAchievement@reportDetailBadgeUser');

        /*Report Membership*/
        Route::any('membership', 'ApiReportAchievement@reportMembership');
        Route::any('membership/detail', 'ApiReportAchievement@reportDetailMembership');
        Route::any('membership/list-user', 'ApiReportAchievement@reportListUserMembership');

        Route::any('list-achivement', 'ApiAchievement@reportAch');
        Route::any('membership-achivement', 'ApiAchievement@reportMembership');
    });
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'scopes:apps'], 'prefix' => 'achievement'], function () {
    Route::any('myachievement', 'ApiAchievement@detailAchievement');
});

Route::middleware('auth:api')->get('/achievement', function (Request $request) {
    return $request->user();
});
