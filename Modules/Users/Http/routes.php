<?php

Route::group(['namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::any('email/verify/{slug}', 'ApiUser@verifyEmail');
});

Route::group(['middleware' => ['auth_client','log_activities', 'user_agent', 'scopes:apps'], 'prefix' => 'api/v2/users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::post('phone/check', 'ApiUserV2@phoneCheck');

    Route::post('pin/forgot', 'ApiUserV2@forgotPin');
});

Route::group(['prefix' => 'api', 'middleware' => ['log_activities', 'user_agent']], function () {
    Route::group(['middleware' => ['auth_client','log_activities', 'user_agent', 'scopes:apps'], 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::post('validation-phone', 'ApiUser@validationPhone');
    });

    Route::group(['middleware' => ['auth_client','log_activities', 'user_agent', 'scopes:apps'], 'prefix' => 'users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::post('social/check', 'ApiLoginRegisterV2@socialCheck');
        Route::post('register', 'ApiLoginRegisterV2@socialCreate')->middleware(['decrypt_pin:user_password']);
        Route::get('department', 'ApiUser@department');
        Route::post('social/bearer', 'ApiLoginRegisterV2@socialGetBearer');
        Route::post('pin/verify', 'ApiLoginRegisterV2@verifyPin')->middleware('decrypt_pin');
        Route::post('pin/check', 'ApiLoginRegisterV2@checkPin')->middleware('decrypt_pin');
        Route::post('pin/resend', 'ApiLoginRegisterV2@resendPin');
        Route::post('phone/check', 'ApiLoginRegisterV2@phoneCheck');
        Route::post('pin/forgot', 'ApiLoginRegisterV2@forgotPin');
        Route::post('pin/change', 'ApiLoginRegisterV2@changePin')->middleware(['decrypt_pin:pin_new','decrypt_pin:pin_old']);
        Route::post('pin/request', 'ApiLoginRegisterV2@pinRequest');
        Route::post('profile/update/register', 'ApiLoginRegisterV2@profileUpdateRegister')->middleware(['decrypt_pin:pin_new','decrypt_pin:pin_old']);
    });

    Route::group(['middleware' => ['auth:api','log_activities', 'user_agent', 'scopes:apps'], 'prefix' => 'users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::post('logout', 'ApiLoginRegisterV2@logout');
        Route::get('profile/detail', 'ApiUser@profileDetail');
        Route::post('profile/update-info', 'ApiUser@profileUpdateInfo');
        Route::post('profile/update-personal', 'ApiUser@profileUpdatePersonal');
        Route::post('profile/update-photo', 'ApiUser@profileUpdatePhoto');
        Route::post('profile/update-password', 'ApiUser@profileUpdatePassword')->middleware(['decrypt_pin:password_old','decrypt_pin:password_new']);
        Route::post('profile/update', 'ApiUser@profileUpdate');
        Route::post('profile/delete', 'ApiUser@userDelete')->middleware('decrypt_pin');
        Route::get('status/count', 'ApiUser@statusAllCount');
    });

    Route::group(['middleware' => ['auth_client','log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::post('pin/check-backend', 'ApiUser@checkPinBackend');
        Route::post('remove-user-device', 'ApiUser@removeUserDevice');
    });
    Route::group(['middleware' => ['auth:api', 'user_agent', 'scopes:apps'], 'prefix' => 'home', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::post('search', 'ApiHome@searchHome');
        Route::post('/membership', 'ApiHome@membership');
        Route::any('/featured-deals', 'ApiHome@featuredDeals');
        Route::any('/featured-subscription', 'ApiHome@featuredSubscription');
        Route::any('/featured-promo-campaign', 'ApiHome@featuredPromoCampaign');
        Route::post('refresh-point-balance', 'ApiHome@refreshPointBalance');
        Route::get('get-infobip-token', ['uses' => 'ApiHome@getInfobipToken']);
    });

    Route::group(['middleware' => ['auth:api', 'scopes:apps'], 'prefix' => 'users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::any('send/email/verify', 'ApiUser@sendVerifyEmail');
    });

    Route::group(['prefix' => 'home', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::any('splash', 'ApiHome@splash');
        Route::any('/banner', 'ApiHome@banner');
        Route::any('background', 'ApiHome@bgCustomer');
        Route::any('notloggedin', 'ApiHome@homeNotLoggedIn');
    });

    Route::group(['prefix' => 'home-doctor', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
        Route::any('splash', 'ApiHome@doctorSplash');
    });
});

Route::group(['middleware' => ['auth:api', 'user_agent', 'scopes:be'], 'namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::get('user-delete/{phone}', ['middleware' => 'feature_control:6', 'uses' => 'ApiUser@deleteUser']);
    Route::post('user-delete/{phone}', ['middleware' => 'feature_control:6', 'uses' => 'ApiUser@deleteUserAction']);
});

Route::group(['prefix' => 'api/cron', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::any('/reset-trx-day', 'ApiUser@resetCountTransaction');
});

Route::group(['middleware' => ['auth:api','log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'api/users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::post('pin/check/be', 'ApiUser@checkPinBackend');
    Route::post('list/address', 'ApiUser@listAddress');
    Route::get('list/{var}', 'ApiUser@listVar');
    Route::post('new', ['middleware' => 'feature_control:4', 'uses' => 'ApiUser@newUser']);
    Route::post('update/profile', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateProfile']);
    Route::post('update/pin', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updatePin']);
    Route::post('update/status', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateStatus']);
    Route::post('update/feature', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateFeature']);
    Route::post('profile', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@profile']);
    Route::post('activate', ['uses' => 'ApiUser@activateUserDeleted']);

    Route::any('summary', 'ApiUser@summaryUsers');
    Route::post('check', 'ApiUser@check');
    Route::post('fitur', 'ApiUser@fitur');

    Route::post('granted-feature', 'ApiUser@getFeatureControl');
    Route::get('rank/list', 'ApiUser@listRank');
    Route::post('create', 'ApiUser@createUserFromAdmin');

    Route::post('list', ['middleware' => 'feature_control:2', 'uses' => 'ApiUser@list']);
    Route::post('department', ['middleware' => 'feature_control:2', 'uses' => 'ApiUser@department']);
    Route::post('adminoutlet/detail', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@detailAdminOutlet']);
    Route::post('adminoutlet/list', ['middleware' => 'feature_control:2', 'uses' => 'ApiUser@listAdminOutlet']);
    Route::post('adminoutlet/create', ['middleware' => 'feature_control:4', 'uses' => 'ApiUser@createAdminOutlet']);
    Route::post('adminoutlet/delete', ['middleware' => 'feature_control:6', 'uses' => 'ApiUser@deleteAdminOutlet']);
    Route::post('activity', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@activity']);
    Route::post('detail', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@show']);
    Route::post('favorite', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@favorite']);
    Route::post('log', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@log']);
    Route::get('log/detail/{id}/{log_type}', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@detailLog']);
    Route::post('delete', ['middleware' => 'feature_control:6', 'uses' => 'ApiUser@delete']);
    Route::post('delete/log', ['middleware' => 'feature_control:6', 'uses' => 'ApiUser@deleteLog']);
    Route::post('update', ['uses' => 'ApiUser@updateProfileByAdmin']);
    Route::post('update/photo', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateProfilePhotoByAdmin']);
    Route::post('update/password', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateProfilePasswordByAdmin']);
    Route::post('update/level', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateProfileLevelByAdmin']);
    Route::post('update/outlet', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateDoctorOutletByAdmin']);
    Route::post('update/permission', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateProfilePermissionByAdmin']);
    Route::post('update/suspend', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateSuspendByAdmin']);
    Route::post('update/outlet', ['middleware' => 'feature_control:5', 'uses' => 'ApiUser@updateUserOutletByAdmin']);
    Route::post('phone/verified', 'ApiUser@phoneVerified');
    Route::post('phone/unverified', 'ApiUser@phoneUnverified');
    Route::post('email/verified', 'ApiUser@emailVerified');
    Route::post('email/unverified', 'ApiUser@emailUnverified');
    Route::post('inbox', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@inboxUser']);
    Route::post('outlet', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@outletUser']);
    Route::any('notification', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@getUserNotification']);
    Route::get('get-all', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@getAllName']);
    Route::any('get-detail', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@getDetailUser']);
    Route::any('getExtraToken', 'ApiUser@getExtraToken');

    // get user profile
    Route::get('get', ['middleware' => 'feature_control:3', 'uses' => 'ApiUser@getUserDetail']);
    // skip completes user profile
    Route::get('complete-profile/later', ['middleware' => 'feature_control:3', 'uses' => 'ApiWebviewUser@completeProfileLater']);
    // submit complete user profile
    Route::post('complete-profile', ['middleware' => 'feature_control:3', 'uses' => 'ApiWebviewUser@completeProfile']);
    // get complete user profile success message
    Route::get('complete-profile/success-message', ['middleware' => 'feature_control:3', 'uses' => 'ApiWebviewUser@getSuccessMessage']);
});



Route::group(['middleware' => ['auth:api','log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'api/mitra/users', 'namespace' => 'Modules\Users\Http\Controllers'], function () {
    Route::get('get', ['uses' => 'ApiUserMitra@getUserDetail']);
});
