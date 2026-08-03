<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MobileSentrixScraper;

class MobileSentrixScrape extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:mobilesentrix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MobileSentrix authentication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $scraper = new MobileSentrixScraper();

            $this->info('Attempting login...');
            $scraper->login();

            $this->info('Login successful.');

            $this->info('Fetching test product page...');
            $scraper->crawl();
            
            $this->info('Done.');

        } catch (\Throwable $e) {
            $this->error('Test failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
