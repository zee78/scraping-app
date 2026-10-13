<?php

namespace App\Console\Commands;

use App\Services\OzyToolboxScraper;
use Illuminate\Console\Command;

class ScrapeOzyToolbox extends Command
{
    protected $signature = 'scrape:ozy-toolbox {--limit=0 : Maximum URLs to process (0 = all)} {--url= : Scrape one product URL only}';

    protected $description = 'Scrape Ozy Toolbox catalogue data, specifications, and product video URLs';

    public function handle(OzyToolboxScraper $scraper): int
    {
        try {
            $result = $scraper->scrape((int) $this->option('limit'), $this->option('url') ?: null);
            $this->info("Stored {$result['stored']} of {$result['discovered']} discovered URLs; exported {$result['exported']} products to {$result['path']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
