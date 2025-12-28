<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */

    protected $commands = [
        \App\Console\Commands\RunKoinisScraper::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            (new \App\Services\Scraper\ScraperManager(
                new \App\Services\Scraper\KoinisScraper()
            ))->run();
        })->dailyAt('00:10');
        $schedule->command('scrape:koinis')->dailyAt('00:00')->runInBackground();

        $schedule->command('products:sync-envirofone --format=csv --limit=2000')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                // optional: notify devs
            });
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
