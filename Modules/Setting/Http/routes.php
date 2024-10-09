<?php

Route::group(['middleware' => ['api', 'log_activities', 'user_agent'], 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::get('/courier', 'ApiSetting@settingCourier');
    Route::get('phone-number', 'ApiSetting@settingPhoneNumber');
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:apps'], 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('/intro/home', 'ApiTutorial@introHomeFrontend');
    Route::any('/faq', 'ApiSetting@faqList');
    Route::get('/webview/faq', 'ApiSetting@faqWebview');
    Route::any('jobs_list', 'ApiSetting@jobsList');
    Route::any('celebrate_list', 'ApiSetting@celebrateList');
    Route::post('webview', 'ApiSetting@settingWebview');
    Route::post('sumber-dana', 'ApiSumberDana@index');

    // complete profile
    Route::group(['prefix' => 'complete-profile'], function () {
        Route::get('/', 'ApiSetting@getCompleteProfile');
        Route::post('/', 'ApiSetting@completeProfile');
        Route::post('/success-page', 'ApiSetting@completeProfileSuccessPage');
    });

    Route::post('/free-delivery', 'ApiSetting@updateFreeDelivery');
    Route::post('/go-send-package-detail', 'ApiSetting@updateGoSendPackage');
});

Route::group(['middleware' => ['auth:doctor-apps', 'log_activities', 'user_agent', 'scopes:doctor-apps'], 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('/faq-doctor-apps', 'ApiSetting@faqListDoctorApps');
});

Route::group(['middleware' => ['auth_client', 'log_activities', 'user_agent'], 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('/', 'ApiSetting@settingList');
    Route::get('/faq', 'ApiSetting@faqList');
    Route::any('/default_home', 'ApiSetting@homeNotLogin');
    Route::any('/default_home_doctor', 'ApiSetting@homeDoctorNotLogin');
    Route::get('/navigation', 'ApiSetting@Navigation');
    Route::get('/navigation-logo', 'ApiSetting@NavigationLogo');
    Route::get('/navigation-sidebar', 'ApiSetting@NavigationSidebar');
    Route::get('/navigation-navbar', 'ApiSetting@NavigationNavbar');
    Route::any('outletapp/splash-screen', 'ApiSetting@splashScreenOutletApps');
    Route::any('social-login', 'ApiSetting@socialLogin');
});

Route::group(['middleware' => ['auth_client', 'log_activities', 'user_agent'], 'prefix' => 'api/version', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::get('/list', 'ApiVersion@getVersion');
    Route::post('/update', 'ApiVersion@updateVersion');
});
Route::group(['middleware' => ['auth_client', 'log_activities', 'user_agent'], 'prefix' => 'api/sumber-dana', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::get('/list', 'ApiSumberDana@getSumberDana');
    Route::post('/update', 'ApiSumberDana@updateSumberDana');
});

Route::group(['prefix' => 'api/version', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::post('/', 'ApiVersion@index');
    Route::post('/doctor-app', 'ApiDoctorAppVersion@index');
});

Route::group(['namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('terms-of-service', 'ApiSetting@viewTOS');
});

Route::group([ 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('webview/{key}', 'ApiSettingWebview@aboutWebview');
    Route::any('/faq/webview', 'ApiSettingWebview@faqWebviewView');
    Route::any('detail/{key}', 'ApiSettingWebview@aboutDetail');
    Route::any('/faq/detail', 'ApiSettingWebview@faqDetailView');
    Route::any('/intro/list', 'ApiTutorial@introListFrontend');
    Route::any('/text_menu_list', 'ApiSetting@textMenuList');
});

Route::group(['middleware' => ['auth:api', 'log_activities', 'user_agent', 'scopes:be'], 'prefix' => 'api/setting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('invoice', 'ApiSetting@settingInvoice');
    Route::any('saveInvoice', 'ApiSetting@saveInvoice');
    Route::any('whatsapp', 'ApiSetting@settingWhatsApp');
    Route::any('be/celebrate_list', 'ApiSetting@celebrateList');
    Route::any('be/jobs_list', 'ApiSetting@jobsList');
    Route::get('be/complete-profile', 'ApiSetting@getCompleteProfile');
    Route::post('be/complete-profile', 'ApiSetting@completeProfile');
    Route::any('be/text_menu_list', 'ApiSetting@textMenuList');
    Route::any('be/faq', 'ApiSetting@faqList');
    Route::any('be/faq-doctor', 'ApiSetting@faqListDoctorApps');
    Route::any('/intro', ['middleware' => 'feature_control:168', 'uses' => 'ApiTutorial@introList']);
    Route::post('/intro/save', ['middleware' => 'feature_control:169', 'uses' => 'ApiTutorial@introSave']);
    Route::post('email', 'ApiSetting@settingEmail');
    Route::any('email/update', 'ApiSetting@emailUpdate');
    Route::get('email', 'ApiSetting@getSettingEmail');
    Route::post('/update', 'ApiSetting@settingUpdate');
    Route::post('/update2', 'ApiSetting@update');

    Route::get('/get/{key}', 'ApiSetting@get');
    Route::any('/', 'ApiSetting@settingList');
    Route::post('/edit', 'ApiSetting@settingEdit');
    Route::post('/date', 'ApiSetting@date');
    Route::any('/app_logo', 'ApiSetting@appLogo');
    Route::any('/app_navbar', 'ApiSetting@appNavbar');
    Route::any('/app_sidebar', 'ApiSetting@appSidebar');
    Route::get('/level', 'ApiSetting@levelList');
    Route::post('/level/create', 'ApiSetting@levelCreate');
    Route::post('/level/edit', 'ApiSetting@levelEdit');
    Route::post('/level/update', 'ApiSetting@levelUpdate');
    Route::any('/level/delete', 'ApiSetting@levelDelete');

    Route::get('/holiday', ['middleware' => 'feature_control:34', 'uses' => 'ApiSetting@holidayList']);
    Route::post('/holiday/create', ['middleware' => 'feature_control:36', 'uses' => 'ApiSetting@holidayCreate']);
    Route::post('/holiday/store', ['middleware' => 'feature_control:36', 'uses' => 'ApiSetting@holidayStore']);
    Route::post('/holiday/edit', ['middleware' => 'feature_control:37', 'uses' => 'ApiSetting@holidayEdit']);
    Route::post('/holiday/update', ['middleware' => 'feature_control:37', 'uses' => 'ApiSetting@holidayUpdate']);
    Route::any('/holiday/delete', ['middleware' => 'feature_control:38', 'uses' => 'ApiSetting@holidayDelete']);
    Route::any('/holiday/detail', ['middleware' => 'feature_control:35', 'uses' => 'ApiSetting@holidayDetail']);

    Route::post('/faq/create', 'ApiSetting@faqCreate');
    Route::post('/faq/edit', 'ApiSetting@faqEdit');
    Route::post('/faq/update', 'ApiSetting@faqUpdate');
    Route::post('/faq/delete', 'ApiSetting@faqDelete');
    Route::post('faq/sort/update', 'ApiSetting@faqSortUpdate');
    Route::post('/faq-doctor/create', 'ApiSetting@faqCreateDoctorApps');
    Route::post('/faq-doctor/edit', 'ApiSetting@faqEditDoctorApps');
    Route::post('/faq-doctor/update', 'ApiSetting@faqUpdateDoctorApps');
    Route::post('/faq-doctor/delete', 'ApiSetting@faqDeleteDoctorApps');
    Route::post('faq-doctor/sort/update', 'ApiSetting@faqSortUpdateDoctorApps');
    Route::post('reset/{type}/update', 'ApiSetting@pointResetUpdate');// point reset
    Route::post('consultation/{type}/update', 'ApiSetting@consultationSettingUpdate');

    /* Menu Setting */
    Route::any('/text_menu/update', ['middleware' => 'feature_control:161', 'uses' => 'ApiSetting@updateTextMenu']);
    Route::get('/text_menu/configs', ['middleware' => 'feature_control:160', 'uses' => 'ApiSetting@configsMenu']);

    /* Phone Setting */
    Route::any('/phone/update', ['middleware' => 'feature_control:210', 'uses' => 'ApiSetting@updatePhoneSetting']);
    Route::get('/phone', ['middleware' => 'feature_control:210', 'uses' => 'ApiSetting@phoneSetting']);

    /* Maintenance Mode */
    Route::post('maintenance-mode/update', ['middleware' => 'feature_control:235', 'uses' => 'ApiSetting@updateMaintenanceMode']);
    Route::get('maintenance-mode', ['middleware' => 'feature_control:235', 'uses' => 'ApiSetting@maintenanceMode']);

    /* Time Expired OTP and Email Verify */
    Route::post('time-expired/update', ['uses' => 'ApiSetting@updateTimeExpired']);
    Route::get('time-expired', ['uses' => 'ApiSetting@timeExpired']);

    Route::group(['middleware' => ['auth:api', 'scopes:be'], 'prefix' => 'dashboard'], function () {
        Route::any('', 'ApiDashboardSetting@getDashboard');
        Route::get('list', 'ApiDashboardSetting@getListDashboard');
        Route::post('update', 'ApiDashboardSetting@updateDashboard');
        Route::post('delete', 'ApiDashboardSetting@deleteDashboard');
        Route::post('update/date-range', 'ApiDashboardSetting@updateDateRange');
        Route::post('update-visibility', 'ApiDashboardSetting@updateVisibilitySection');
        Route::post('order-section', 'ApiDashboardSetting@updateOrderSection');
        Route::post('order-card', 'ApiDashboardSetting@updateOrderCard');
    });

    // banner
    Route::group(['middleware' => ['auth:api', 'scopes:be'], 'prefix' => 'banner'], function () {
        Route::get('list', ['middleware' => 'feature_control:144', 'uses' => 'ApiBanner@index']);
        Route::post('create', ['middleware' => 'feature_control:145', 'uses' => 'ApiBanner@create']);
        Route::post('update', ['middleware' => 'feature_control:146', 'uses' => 'ApiBanner@update']);
        Route::post('reorder', ['middleware' => 'feature_control:145', 'uses' => 'ApiBanner@reorder']);
        Route::post('delete', ['middleware' => 'feature_control:147', 'uses' => 'ApiBanner@destroy']);
    });

    // featured_deal
    Route::group(['middleware' => ['auth:api', 'scopes:be'], 'prefix' => 'featured_deal'], function () {
        Route::get('list', 'ApiFeaturedDeal@index');
        Route::post('create', 'ApiFeaturedDeal@create');
        Route::post('update', 'ApiFeaturedDeal@update');
        Route::post('reorder', 'ApiFeaturedDeal@reorder');
        Route::post('delete', 'ApiFeaturedDeal@destroy');
    });

    // featured subscription
    Route::group(['middleware' => ['auth:api', 'scopes:be'], 'prefix' => 'featured_subscription'], function () {
        Route::get('list', 'ApiFeaturedSubscription@index');
        Route::post('create', 'ApiFeaturedSubscription@create');
        Route::post('update', 'ApiFeaturedSubscription@update');
        Route::post('reorder', 'ApiFeaturedSubscription@reorder');
        Route::post('delete', 'ApiFeaturedSubscription@destroy');
    });

    // featured promo campaign
    Route::group(['middleware' => ['auth:api', 'scopes:be'], 'prefix' => 'featured_promo_campaign'], function () {
        Route::get('list-merchant', 'ApiFeaturedPromoCampaign@indexMerchant');
        Route::get('list', 'ApiFeaturedPromoCampaign@index');
        Route::post('create', 'ApiFeaturedPromoCampaign@create');
        Route::post('update', 'ApiFeaturedPromoCampaign@update');
        Route::post('reorder', 'ApiFeaturedPromoCampaign@reorder');
        Route::post('delete', 'ApiFeaturedPromoCampaign@destroy');
    });

    //max consultation quota
    Route::post('/max-consultation/update', 'ApiSetting@maxConsultationQuota');
});

Route::group(['prefix' => 'api/timesetting', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::get('/', 'ApiGreetings@listTimeSetting');
    Route::post('/', 'ApiGreetings@updateTimeSetting');
});

Route::group(['prefix' => 'api/background', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('/', 'ApiBackground@listBackground');
    Route::post('create', 'ApiBackground@createBackground');
    Route::post('delete', 'ApiBackground@deleteBackground');
});

Route::group(['prefix' => 'api/greetings', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
    Route::any('/', 'ApiGreetings@listGreetings');
    Route::post('selected', 'ApiGreetings@selectGreetings');
    Route::post('create', 'ApiGreetings@createGreetings');
    Route::post('update', 'ApiGreetings@updateGreetings');
    Route::post('delete', 'ApiGreetings@deleteGreetings');
});
Route::group(['prefix' => 'api/cs', 'namespace' => 'Modules\Setting\Http\Controllers'], function () {
   Route::any('/', 'ApiSetting@settingWhatsApp');
});
