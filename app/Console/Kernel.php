<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('alerts:check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('funnel_alert:check')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('exchange:sync')
            ->daily()
            ->runInBackground();

        $schedule->command('volume:alerts')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
