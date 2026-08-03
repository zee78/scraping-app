<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UtopyaScraper;

class RunUtopyaScraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:run-utopya-scraper';

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
        try {
            $scraper = new UtopyaScraper();
            $scraper->scrape();
            // $this->info('Attempting login...');
            // $scraper->login();
            // $this->info('Login successful.');

            // $scraper->verifyLogin();
            // $this->info('Session verified.');
            // $scraper->scrapeCategories();
            // $html = $scraper->get('/gas-gauge-for-ipad-pro-12-9-5th-gen-2021-u8900');

            // if (str_contains($html, '£') || str_contains($html, 'Price')) {
            //     $this->info('Authenticated content detected (price visible).');
            // } else {
            //     $this->error('Price not visible. Login may have failed.');
            // }

        } catch (\Throwable $e) {
            $this->error('Test failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
