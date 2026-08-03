<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AccupartScraper;

class ScrapeAccupart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:accupart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape products from Accupart';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Accupart scraping...');

        try {
            $scraper = new AccupartScraper();
            $scraper->scrapeAllProducts();

            $this->info('Scraping completed successfully.');
            $scraper->exportToCsv();
            $this->info('Csv file generated: storage/app/public/accupart-products.csv');
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
