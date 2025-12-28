<?php

namespace App\Services\Scraper;

interface ScraperInterface
{
    public function crawl();
    public function parseProductPage(string $url);
}

// php artisan scrape:koinis --single="https://koinis.gr/el/%CE%91%CF%81%CF%87%CE%B9%CE%BA%CE%AE/41474-51857-comfeet-m02913-13.html"
