<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scraper\ScraperManager;
use App\Services\Scraper\KoinisScraper;
use App\Repositories\ProductRepository;

class RunKoinisScraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:koinis {--single=}';
    protected ProductRepository $repo;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Koinis scraper (optionally parse a single URL via --single="https://...")';

    /**
     * Execute the console command.
     */

    public function __construct(ProductRepository $repo)
    {
        parent::__construct();
        $this->repo = $repo;
    }

    public function handle()
    {
        $this->info('Starting Koinis scrape...');
        $scraper = new KoinisScraper();
        $singleUrl = $this->option('single');

        if ($singleUrl) {
            $this->info("Scraping single URL: $singleUrl");
            $scraper->crawlSingle($singleUrl);
            // $this->repo->exportCsv('koinis_products.csv');

        } else {
            $this->info("Scraping full site...");
            $scraper->crawl();
            // $this->repo->exportCsv('koinis_products.csv');
        }
        $this->info('Scrape finished. Exporting data to CSV...');
        
        // Export all products to CSV
        // $filename = 'koinis_products.csv';
        $this->repo->conditionalExportCsv();

        $this->info("CSV exported successfully: ");
    }
}
