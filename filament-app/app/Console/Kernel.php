<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Audit and validate polling system integrity every 10 minutes
        $schedule->command('polling:reliable audit')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
            
        // Validate polling integrity every 5 minutes
        $schedule->command('polling:reliable validate')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
            
        // Automatically fix polling schedule issues every 15 minutes
        $schedule->command('polling:fix-schedule')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
