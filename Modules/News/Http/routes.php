<?php

Route::group(['middleware' => ['auth:api','user_agent', 'scopes:apps', 'log_activities'], 'prefix' => 'api/news', 'namespace' => 'Modules\News\Http\Controllers'], function () {
    Route::any('list', 'ApiNews@listNews');
    Route::any('category', 'ApiNewsCategoryController@index');
});

Route::group(['prefix' => 'api/news', 'middleware' => ['log_activities', 'auth:api','user_agent', 'scopes:apps'], 'namespace' => 'Modules\News\Http\Controllers'], function () {
        Route::any('list/test', 'ApiNews@listNews');
        // Route::any('list/web', 'ApiNews@listNews');
        // Route::any('list', 'ApiNews@listNews');
        Route::any('webview', 'ApiNews@webview');
        Route::any('/detail', 'ApiNewsWebview@detailNews');
});


Route::group(['prefix' => 'news', 'namespace' => 'Modules\News\Http\Controllers','middleware' => ['auth:api', 'scopes:apps']], function () {
    Route::any('/webview/{id}', 'ApiNewsWebview@detail');
});

Route::group(['middleware' => ['auth:api','user_agent','log_activities', 'scopes:be'], 'prefix' => 'api/news', 'namespace' => 'Modules\News\Http\Controllers'], function () {
    Route::get('featured', 'ApiNews@featured');
    Route::post('featured', 'ApiNews@featured');

    Route::any('be/list', ['middleware' => 'feature_control:19', 'uses' => 'ApiNews@listNews']);
    Route::any('be/category', ['middleware' => 'feature_control:164', 'uses' => 'ApiNews@listCategory']);

    Route::post('get', 'ApiNews@getNewsById');// get news for custom form webview
    Route::post('custom-form', 'ApiNews@customForm');// submit custom form webview (user not login)
    Route::post('custom-form/file', 'ApiNews@customFormUploadFile');// upload file in custom form webview

    Route::post('create', ['middleware' => 'feature_control:21', 'uses' => 'ApiNews@create']);
    Route::post('create/relation', ['middleware' => 'feature_control:21', 'uses' => 'ApiNews@createRelation']);
    Route::post('delete/relation', ['middleware' => 'feature_control:23', 'uses' => 'ApiNews@deleteRelation']);
    Route::post('update', ['middleware' => 'feature_control:22', 'uses' => 'ApiNews@update']);
    Route::post('delete', ['middleware' => 'feature_control:23', 'uses' => 'ApiNews@delete']);
    Route::post('form-data', 'ApiNews@formData');// get news form data
    Route::post('custom-form/auth', 'ApiNews@customForm');// submit custom form webview (user logged in)
    Route::get('position/list', ['uses' => 'ApiNews@positionListNews']);
    Route::post('position/assign', ['uses' => 'ApiNews@positionNews']);

    Route::any('be/category', ['middleware' => 'feature_control:164', 'uses' => 'ApiNewsCategoryController@index']);
    Route::group(['prefix' => 'category'], function () {
        Route::post('create', ['middleware' => 'feature_control:165', 'uses' => 'ApiNewsCategoryController@store']);// create news category
        Route::post('update', ['middleware' => 'feature_control:166', 'uses' => 'ApiNewsCategoryController@update']);// update news category
        Route::post('delete', ['middleware' => 'feature_control:167', 'uses' => 'ApiNewsCategoryController@destroy']);// delete news category
        Route::post('position/assign', ['uses' => 'ApiNewsCategoryController@positionCategory']);
    });
});

Route::group(['middleware' => ['auth:api','user_agent', 'scopes:apps', 'log_activities'], 'prefix' => 'api/e-learning', 'namespace' => 'Modules\News\Http\Controllers'], function () {
    Route::any('home', 'ApiElearning@home');
    Route::any('video', 'ApiElearning@videoList');
    Route::post('video/detail', 'ApiElearning@videoDetail');
    Route::any('article', 'ApiElearning@articleList');
    Route::post('article/detail', 'ApiElearning@articleDetail');
    Route::get('online-class/banner', 'ApiElearning@onlineClassBanner');
    Route::any('online-class', 'ApiElearning@onlineClassList');
    Route::post('online-class/detail', 'ApiElearning@onlineClassDetail');
    Route::any('favorite/list', 'ApiElearning@favoriteList');
    Route::post('favorite/add', 'ApiElearning@favoriteAdd');
    Route::post('favorite/delete', 'ApiElearning@favoriteDelete');
});
