<?php

use Illuminate\Http\Request;
use App\Http\Models\Setting;
use App\Lib\MyHelper;

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

Route::middleware(['auth:api', 'scopes:be'])->get('/user', function (Request $request) {
    return json_decode($request->user(), true);
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'scopes:be'] ], function () {
    Route::get('granted-feature', 'Controller@getFeatureControl');
    Route::get('feature', 'Controller@getFeature');
    Route::get('feature-module', 'Controller@getFeatureModule');
    Route::get('rank/list', 'Controller@listRank');
    Route::get('config', 'Controller@getConfig');
    Route::get('sidebar-badge', 'Controller@getSidebarBadge');
    // Route::any('city/list', 'Controller@listCity');
    // Route::get('province/list', 'Controller@listProvince');
    Route::post('summernote/upload/image', 'Controller@uploadImageSummernote');
    Route::post('summernote/delete/image', 'Controller@deleteImageSummernote');
});

/* NO AUTH */
Route::get('decode/{id}', 'Controller@decode');
Route::get('maintenance-mode', 'Controller@maintenance');
Route::any('department/list', 'Controller@listDepartment');
Route::any('city/list', 'Controller@listCity');
Route::get('province/list', 'Controller@listProvince');
Route::any('district/list', 'Controller@listDistrict');
Route::any('subdistrict/list', 'Controller@listSubdistrict');
Route::get('courier/list', 'Controller@listCourier');
Route::get('time', function () {
    date_default_timezone_set('Asia/Jakarta');
    $am = App\Http\Models\Setting::where('key', 'processing_time')->first();
    $ptt = Setting::select('value_text')->where('key', 'processing_time_text')->pluck('value_text')->first() ?: 'Set pickup time minimum %processing_time% minutes from now';
    return response()->json([
        'time' => date('Y-m-d H:i:s'),
        'processing' => $am['value'],
        'new_format' => date('Y-m-d H:i:s+0000'),
        'time_add' => date('Y-m-d H:i:s+0000', strtotime('+ ' . $am['value'] . ' minutes')),
        'message' => MyHelper::simpleReplace($ptt, ['processing_time' => $am['value']])
    ]);
});
