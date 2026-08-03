<?php

namespace App\Console\Commands;

use App\Services\ScreenSurgeryScraper;
use Illuminate\Console\Command;

class ScrapeScreenSurgery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:screensurgery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Screen Surgery scraper...');

        try {
            $scraper = new ScreenSurgeryScraper();
            $scraper->scrapeAll();

            $this->info('Scraping completed successfully.');
            $this->info('Csv file generated: storage/app/public/screensurgery-products.csv');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
    }
}
