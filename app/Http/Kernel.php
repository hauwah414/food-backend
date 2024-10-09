<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Fruitcake\Cors\HandleCors::class,
        // \App\Http\Middleware\TrustProxies::class,
        //\Fideloper\Proxy\TrustProxies::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // 'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // 'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth' => \SMartins\PassportMultiauth\Http\Middleware\MultiAuthenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth_client' => \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
        'auth_pos' => \Modules\OutletApp\Http\Middleware\AuthPOS::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'log_activities'    => \App\Http\Middleware\LogActivitiesMiddleware::class,
        'log_activities_pos'    => \App\Http\Middleware\LogActivitiesPOSMiddleware::class,
        'log_activities_pos_transaction'    => \App\Http\Middleware\LogActivitiesPOSTransactionMiddleware::class,
        'log_activities_outlet_apps'    => \App\Http\Middleware\LogActivitiesOutletAppsMiddleware::class,
        'oauth.providers' => \SMartins\PassportMultiauth\Http\Middleware\AddCustomProvider::class,
        'custom_auth'    => \App\Http\Middleware\CustomAuth::class,
        'feature_control'   => \App\Http\Middleware\FeatureControl::class,
        'user_agent'   => \App\Http\Middleware\UserAgentControl::class,
        'scopes' => \App\Http\Middleware\CheckScopes::class,
        'outlet_device_location' => \App\Http\Middleware\VerifyOutletDeviceLocation::class,
    ];
}
