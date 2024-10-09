<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
      
//        $schedule->call('Modules\Transaction\Http\Controllers\ApiShipperController@cronCompletedReceivedOrder')->everyMinute();

        $schedule->call('Modules\Transaction\Http\Controllers\ApiCron@orderReceived')->dailyAt('23:59');
        
        $schedule->call('Modules\Transaction\Http\Controllers\ApiTransactionGroup@cronJob')->everyMinute();
//         $schedule->call('Modules\Transaction\Http\Controllers\ApiTransactionGroup@cronJobReminder')->everyTenMinutes();

        // $schedule->call('Modules\Merchant\Http\Controllers\ApiMerchantTransactionController@autoCancel')->dailyAt(config('app.env') == 'staging' ? '10:52' : '00:06');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
