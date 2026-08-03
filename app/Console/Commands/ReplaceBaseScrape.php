<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReplaceBaseScraper;

class ReplaceBaseScrape extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:replace-base';

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
            $scraper = new ReplaceBaseScraper();
            $scraper->scrape();
        } catch (\Throwable $e) {
            $this->error('Test failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
