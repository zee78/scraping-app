<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Scraper\ScraperManager;
use App\Services\Scraper\KoinisScraper;

class CrawlerController extends Controller
{
    protected ScraperManager $manager;

    public function __construct(ScraperManager $manager)
    {
        $this->manager = $manager;
    }
    public function crawl()
    {
        $this->manager->run();
        return response()->json(['message' => 'Crawling started'], 200);
    }
    public function run(Request $request)
    {
        $mode = $request->input('mode', 'full');

        if ($mode === 'single' && $request->filled('url')) {
            $url = $request->input('url');
            // If KoinisScraper supports parseProductPage publicly:
            $scraper = new KoinisScraper();
            $data = $scraper->parseProductPage($url);
            return response()->json(['status' => 'ok', 'parsed' => (bool)$data]);
        }

        // full run via manager
        // If you prefer queued jobs for the full run, change KoinisScraper to dispatch jobs
        $this->manager->run();

        return response()->json(['status' => 'started']);
    }

    /**
     * Health check/status of last run (simple)
     */
    public function status()
    {
        // Minimal example: return last entry from ScrapeLog if exists
        $last = \App\Models\ScrapeLog::latest()->first();
        return response()->json(['last_run' => $last]);
    }
}
