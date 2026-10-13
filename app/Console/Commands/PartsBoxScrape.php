<?php

namespace App\Console\Commands;

use App\Services\PartsBoxScraper;
use Illuminate\Console\Command;

class PartsBoxScrape extends Command
{
    protected $signature = 'scrape:partsbox
        {--limit=0 : Maximum number of product pages to export (0 = all)}
        {--catalogue= : Override the catalogue path, e.g. /portal/products}';

    protected $description = 'Export available non-accessory PartsBox products to CSV';

    public function handle(PartsBoxScraper $scraper): int
    {
        try {
            $result = $scraper->scrape(
                (int) $this->option('limit'),
                $this->option('catalogue') ?: null,
            );

            $this->info("Exported {$result['exported']} products to {$result['path']}");
            $this->line("Skipped {$result['out_of_stock']} out-of-stock and {$result['accessories']} accessory products.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $safeMessage = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'PartsBox browser request failed. See the safe PartsBox log entries for the processing stage.';
            logger()->error('PartsBox scrape failed.', [
                'message' => $safeMessage,
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error($safeMessage);

            return self::FAILURE;
        }
    }
}
