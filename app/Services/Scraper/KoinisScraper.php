<?php

namespace App\Services\Scraper;

use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use App\Repositories\ProductRepository;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class KoinisScraper implements ScraperInterface
{
    protected string $baseUrl = 'https://koinis.gr/el/';
    protected Client $httpClient;
    protected ProductRepository $repo;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            // allow redirects
            'allow_redirects' => true,
        ]);
        $this->repo = new ProductRepository();
    }


    public function crawl(): void
    {
        $parent = $this;

        Crawler::create()
            ->setCrawlObserver(new class($parent) extends CrawlObserver {
                protected KoinisScraper $parent;

                public function __construct($parent)
                {
                    $this->parent = $parent;
                }

                public function crawled(
                    UriInterface $url,
                    ResponseInterface $response,
                    ?UriInterface $foundOnUrl = null,
                    ?string $linkText = null
                ): void {
                    $urlStr = (string) $url;

                    if (str_starts_with($urlStr, 'https://koinis.gr/en/')) {
                        return;
                    }

                    if (!str_starts_with($urlStr, 'https://koinis.gr/el')) {
                        echo "[SKIPPED] Non-Greek URL: $urlStr\n";
                        return;
                    }
                    $path = (string) $url->getPath();

                    // product page detection: ends with "-*.html" or contains numeric id then slug
                    if (preg_match('/\/(\d+)-.*\.html$/u', $path) || preg_match('/\/\p{L}+\//u', $path) && preg_match('/\/\d+[-_].*\.html$/u', $path)) {

                        echo "[PROCESS] Product: {$url}\n";
                        try {
                            $this->parent->parseProductPage((string)$url);
                        } catch (\Throwable $e) {
                            echo "[PARSE ERROR] {$e->getMessage()}\n";
                        }
                    }
                }

                public function crawlFailed(
                    UriInterface $url,
                    RequestException $exception,
                    ?UriInterface $foundOnUrl = null,
                    ?string $linkText = null
                ): void {
                    echo "[FAILED] $url — {$exception->getMessage()}\n";
                }
            })
            ->setConcurrency(2) // reduce concurrency if site is fragile
            ->startCrawling($this->baseUrl);
    }

    public function crawlSingle(string $url): ?array
    {
        echo "[PROCESS] Scraping single URL: $url\n";

        // ---- Add URL filter here ----
        if (str_starts_with($url, 'https://koinis.gr/en/')) {
            return null;
        }

        if (!str_starts_with($url, 'https://koinis.gr/el/')) {
            echo "[SKIPPED] Non-Greek product URL, skipping: $url\n";
            return null;
        }
        try {
            $data = $this->parseProductPage($url); 
            if ($data) {
                // $this->repo->exportCsv('koinis_products.csv');
                echo "[SUCCESS] Product scraped successfully.\n";
            } else {
                echo "[NO DATA] No product data found.\n";
            }
            return $data;
        } catch (\Throwable $e) {
            echo "[ERROR] Failed to scrape $url — {$e->getMessage()}\n";
            return null;
        }
    }
    

    public function parseProductPage(string $url): ?array
    {
        $maxRetries = 3;
        $attempt = 0;
        $res = null;

        while ($attempt < $maxRetries) {
            try {
                $res = $this->httpClient->get($url);
                break;
            } catch (\Throwable $e) {
                $attempt++;
                $wait = 2 ** $attempt;
                echo "[HTTP ERROR] Attempt {$attempt} for {$url}: {$e->getMessage()}; sleeping {$wait}s\n";
                sleep($wait);
            }
        }

        if (!$res || $res->getStatusCode() !== 200) {
            echo "[SKIP] {$url} returned status " . ($res ? $res->getStatusCode() : 'no response') . "\n";
            return null;
        }

        $html = (string) $res->getBody();

        // optional: save debug HTML for inspection
        $this->saveDebugHtml($url, $html);

        $crawler = new DomCrawler($html);

        $parser = new ProductDataParser();
        $data = $parser->parse($crawler, $url);

        if ($data) {
            $this->repo->storeOrUpdate($data);
        } else {
            echo "[NO DATA] parsing produced empty result for {$url}\n";
        }

        return $data;
    }

    protected function saveDebugHtml(string $url, string $html): void
    {
        try {
            $filename = storage_path('app/debug/koinis_' . md5($url) . '.html');
            if (!file_exists(dirname($filename))) {
                mkdir(dirname($filename), 0755, true);
            }
            file_put_contents($filename, $html);
        } catch (\Throwable $e) {
            // ignore filesystem errors
        }
    }
}
