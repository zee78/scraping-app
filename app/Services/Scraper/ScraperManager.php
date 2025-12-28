<?php

namespace App\Services\Scraper;

class ScraperManager
{
    protected $scraper;

    public function __construct(ScraperInterface $scraper)
    {
        $this->scraper = $scraper;
    }

    public function run()
    {
        return $this->scraper->crawl();
    }
}
