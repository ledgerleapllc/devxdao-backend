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
        'App\Console\Commands\CheckVote',
        'App\Console\Commands\CheckSimpleVote',
        'App\Console\Commands\CheckAdminGrantVote',
        'App\Console\Commands\CheckAdvancePaymentVote',
        'App\Console\Commands\CheckMilestoneVote',
        'App\Console\Commands\CheckProposal',
        'App\Console\Commands\CryptoPrice',
        'App\Console\Commands\ShuftiproCheck'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('shuftipro:check')
        //     ->everyFiveMinutes()
        //     ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('vote:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('simplevote:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('admingrantvote:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('advance-payment-vote:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('milestonevote:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('proposal:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('cryptoprice:check')
            ->everyThirtyMinutes()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('va:daily-summary')
            ->dailyAt('15:00')
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('admin:report')
            ->dailyAt('04:00')
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('survey:check')
            ->everyMinute()
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('total-rep:statistic')
            ->lastDayOfMonth('11:00')
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('daily-reputation')
            ->dailyAt('07:00')
            ->runInBackground();
            // ->withoutOverlapping();
        $schedule->command('kangaroo:check')
            ->everyFiveMinutes()
            ->runInBackground();
            // ->withoutOverlapping();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
