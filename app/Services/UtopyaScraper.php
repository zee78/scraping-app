<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Browsershot\Browsershot;

class UtopyaScraper
{
    protected Client $client;
    protected CookieJar $cookies;
    protected int $productConcurrency = 10;
    protected array $categories = [
        'https://www.utopya.uk/xiaomi',
        'https://www.utopya.uk/samsung',
        'https://www.utopya.uk/apple',
        'https://www.utopya.uk/honor',
        'https://www.utopya.uk/motorola',
        'https://www.utopya.uk/autres-marques'
        // 'https://www.utopya.uk/xiaomi/redmi-note',
        // 'https://www.utopya.uk/xiaomi/mi.html',
        // 'https://www.utopya.uk/xiaomi/xiaomi',
        // 'https://www.utopya.uk/xiaomi/poco.html',
        // 'https://www.utopya.uk/xiaomi/mobilite-urbaine',
        // 'https://www.utopya.uk/xiaomi/pad',
        // 'https://www.utopya.uk/samsung/galaxy-a.html',
        // 'https://www.utopya.uk/samsung/galaxy-s.html',
        // 'https://www.utopya.uk/samsung/galaxy-m.html',
        // 'https://www.utopya.uk/samsung/galaxy-j.html',
        // 'https://www.utopya.uk/samsung/galaxy-z.html',
        // 'https://www.utopya.uk/samsung/galaxy-tab.html',
        // 'https://www.utopya.uk/samsung/galaxy-xcover.html',
        // 'https://www.utopya.uk/samsung/galaxy-note.html',
        // 'https://www.utopya.uk/samsung/galaxy-watch.html',
        // 'https://www.utopya.uk/apple/iphone.html',
        // 'https://www.utopya.uk/apple/apple-watch.html',
        // 'https://www.utopya.uk/apple/ipad.html',
        // 'https://www.utopya.uk/apple/ipad-pro.html',
        // 'https://www.utopya.uk/apple/ipad-mini.html',
        // 'https://www.utopya.uk/apple/ipad-air.html',
        // 'https://www.utopya.uk/apple/macbook.html',
        // 'https://www.utopya.uk/honor/magic.html',
        // 'https://www.utopya.uk/honor/honor-n.html',
        // 'https://www.utopya.uk/honor/honor-x.html',
        // 'https://www.utopya.uk/honor/autres.html',
        // 'https://www.utopya.uk/motorola/razr.html',
        // 'https://www.utopya.uk/motorola/moto-g.html',
        // 'https://www.utopya.uk/motorola/moto-e.html',
        // 'https://www.utopya.uk/motorola/edge.html',
        // 'https://www.utopya.uk/motorola/autres.html',
        // 'https://www.utopya.uk/others.html',
        // 'https://www.utopya.uk/accessories.html',
        // 'https://www.utopya.uk/protection.html',
        // 'https://www.utopya.uk/computing.html',
        // 'https://www.utopya.uk/tools.html'
        // add more later
    ];
  protected array $visitedUrls = [];

    public function __construct()
    {
        $this->cookies = new CookieJar();

    $this->client = new Client([
        'base_uri' => 'https://www.utopya.uk/',
        'cookies'  => $this->cookies,
        'timeout'  => 30,
        'http_errors' => true,
        'headers'  => [
            'User-Agent' =>
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
                '(KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Accept' =>
                'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
        ],
        'curl' => [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => '', 
        ],
    ]);
    }
  

    protected function throttle(): void
    {
        usleep(random_int(500000, 1500000));
    }

    protected function getCategoryHtml(string $url): string
    {
        return Browsershot::url($url)
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36')
            ->setChromePath('/usr/bin/google-chrome')
            ->noSandbox()
            ->addChromiumArguments([
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-features=UseDBus',
                '--no-zygote',
                '--single-process',
            ])
            ->waitFor('.listing-item', 25000)
            ->evaluateScript('
            async () => {
                const sleep = ms => new Promise(r => setTimeout(r, ms));
                let lastHeight = 0;
                let sameHeightTimes = 0;

                while (sameHeightTimes < 2) {
                    window.scrollTo(0, document.body.scrollHeight);
                    await sleep(1000);

                    const newHeight = document.body.scrollHeight;
                    if (newHeight === lastHeight) {
                        sameHeightTimes++;
                    } else {
                        sameHeightTimes = 0;
                        lastHeight = newHeight;
                    }
                }

                await sleep(1500);
            }
        ')
            ->bodyHtml();
    }

    protected function getProductHtml(string $url): string
    {
        return (string) $this->client->get($url)->getBody();
    }

    public function get(string $url): string
    {
        $this->throttle();
        return $this->getCategoryHtml($url);
    }



    protected function normalizeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim(html_entity_decode($url));

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        if (str_starts_with($url, '/media/')) {
            return 'https://www.utopya.uk' . $url;
        }

        return 'https://www.utopya.uk/media/catalog/product/' . ltrim($url, '/');
    }

    public function scrape(): void
    {
        foreach ($this->categories as $categoryUrl) {
            $this->scrapeCategory($categoryUrl);
        }

        $this->exportToCsv();
    }

    protected function getTotalProductsCount(Crawler $crawler): int
    {
        $countText = $crawler->filter('#toolbar-amount .toolbar-number')->text() ?? '';
        return (int) filter_var($countText, FILTER_SANITIZE_NUMBER_INT);
        
    }


    protected function scrapeCategory(string $url): void
    {
        $page = 1;
        $totalProducts = null;
        $productsPerPage = 100;

        while (true) {
            dump('starting scrape for category', $url);
            $pageUrl = $url . (str_contains($url, '?') ? '&' : '?') . 'p=' . $page;

            dump("Scraping page {$page} of category", $pageUrl);

            $this->throttle();
            $html = $this->getCategoryHtml($pageUrl);
            $crawler = new Crawler($html);

            if ($page === 1) {
                $totalProducts = $this->getTotalProductsCount($crawler);
                dump('Total products in category:', $totalProducts);
            }

            $items = $crawler->filter('.listing-item');
            dump('Products found:', $items->count());
            if ($items->count() === 0) {
                break;
            }

            $productUrls = [];
            $items->each(function (Crawler $node) use (&$productUrls) {
                if (!$node->filter('.product-item-link.name')->count()) {
                    return;
                }

                $productUrl = $node->filter('.product-item-link.name')->attr('href');
                if ($productUrl) {
                    $productUrls[] = $productUrl;
                }
            });

            if (!empty($productUrls)) {
                $this->scrapeProductsConcurrently(array_values(array_unique($productUrls)));
            }

            $maxPage = $totalProducts ? (int) ceil($totalProducts / $productsPerPage) : 1;
            if ($page >= $maxPage) {
                dump("Reached last page {$page}. Moving to next category.");
                break;
            }

            $page++;
        }
    }

    protected function scrapeProductsConcurrently(array $urls): void
    {
        $payloads = [];

        $requests = function () use ($urls) {
            foreach ($urls as $url) {
                yield new Request('GET', $url);
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $this->productConcurrency,
            'fulfilled' => function ($response, $index) use ($urls, &$payloads) {
                $url = $urls[$index];
                try {
                    $payload = $this->buildProductPayload((string) $response->getBody(), $url);
                    if ($payload !== null) {
                        $payloads[] = $payload;
                    }
                } catch (\Throwable $e) {
                    logger()->error('Product parse failed', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                }
            },
            'rejected' => function ($reason, $index) use ($urls) {
                logger()->error('Product fetch failed', [
                    'url' => $urls[$index],
                    'error' => method_exists($reason, 'getMessage') ? $reason->getMessage() : (string) $reason,
                ]);
            },
        ]);

        $pool->promise()->wait();

        if (!empty($payloads)) {
            $this->upsertProductBatch($payloads);
        }
    }

    protected function buildProductPayload(string $html, string $productUrl): ?array
    {
        $crawler = new Crawler($html);

        $name = $crawler->filter('h1.product.block-title span.base')->count()
            ? trim($crawler->filter('h1.product.block-title span.base')->text())
            : null;

        if (!$name) {
            return null;
        }

        $sku = $crawler->filter('li.attr-sku strong.data')->count()
            ? trim($crawler->filter('li.attr-sku strong.data')->text())
            : null;

        $ean = $crawler->filter('li.attr-ean strong.data')->count()
            ? trim($crawler->filter('li.attr-ean strong.data')->text())
            : null;

        $mpn = $crawler->filter('li.attr-reference_fabricant strong.data')->count()
            ? trim($crawler->filter('li.attr-reference_fabricant strong.data')->text())
            : null;

        if ($crawler->filter('.price-box span.price')->count()) {
            $priceIncVatRaw = $crawler->filter('.price-box span.price')->first()->text();
            $priceExVat = (float) str_replace(['£', ',', ' ', "\xc2\xa0"], ['', '.', '', ''], $priceIncVatRaw);
        } else {
            $priceExVat = null;
        }

        if ($priceExVat === null) {
            logger()->info('Skipping product without price', ['url' => $productUrl]);
            return null;
        }

        $availability = $crawler->filter('.stock.color-green')->count()
            ? 'in_stock'
            : 'out_of_stock';

        $mainImage = $crawler->filter('link[itemprop="image"]')->count()
            ? $crawler->filter('link[itemprop="image"]')->attr('href')
            : null;

        $additionalImages = $crawler->filter('.product-slider img.zoom')->count()
            ? array_values(array_unique(
                $crawler->filter('.product-slider img.zoom')
                    ->each(fn ($img) => $this->normalizeUrl($img->attr('src')))
            ))
            : [];

        $description = $crawler->filter('.content-description')->count()
            ? trim($crawler->filter('.content-description')->html())
            : null;

        return [
            'sku' => $sku,
            'title' => $name,
            'price' => $priceExVat,
            'availability' => $availability,
            'main_image' => $mainImage,
            'additional_images' => $additionalImages,
            'description' => $description,
            'link' => $productUrl,
            'shop_url' => 'www.utopya.com',
            'reference' => $mpn,
            'stock' => $availability === 'in_stock' ? 10 : 0,
            'ean' => $ean,
        ];
    }

    protected function upsertProductBatch(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $rows = $this->deduplicateBatchRows($rows);
        $now = now()->toDateTimeString();

        foreach ($rows as &$row) {
            if (isset($row['additional_images']) && is_array($row['additional_images'])) {
                $row['additional_images'] = json_encode(array_values($row['additional_images']));
            }
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        $skus = array_values(array_filter(array_column($rows, 'sku')));
        $existingMap = [];

        if (!empty($skus)) {
            DB::table('products')
                ->select('id', 'sku')
                ->where('shop_url', 'www.utopya.com')
                ->whereIn('sku', $skus)
                ->orderBy('id')
                ->each(function ($product) use (&$existingMap) {
                    if (!isset($existingMap[$product->sku])) {
                        $existingMap[$product->sku] = $product->id;
                    }
                });
        }

        $toInsert = [];
        $toUpdate = [];

        foreach ($rows as $row) {
            $sku = $row['sku'] ?? null;
            if ($sku && isset($existingMap[$sku])) {
                unset($row['created_at']);
                $row['id'] = $existingMap[$sku];
                $toUpdate[] = $row;
            } else {
                $toInsert[] = $row;
            }
        }

        if (!empty($toInsert)) {
            DB::table('products')->insert($toInsert);
        }

        if (!empty($toUpdate)) {
            $this->bulkUpdateProducts($toUpdate);
        }
    }

    protected function bulkUpdateProducts(array $rows): void
    {
        $columns = [
            'title', 'description', 'link', 'main_image', 'additional_images',
            'price', 'reference', 'stock', 'shop_url', 'availability', 'ean', 'updated_at',
        ];

        foreach (array_chunk($rows, 200) as $chunk) {
            $chunkIds = array_column($chunk, 'id');
            $setClauses = [];
            $bindings = [];

            foreach ($columns as $column) {
                $when = '';
                foreach ($chunk as $row) {
                    $when .= ' WHEN ? THEN ?';
                    $bindings[] = $row['id'];
                    $bindings[] = $row[$column] ?? null;
                }
                $setClauses[] = "`{$column}` = CASE `id`{$when} ELSE `{$column}` END";
            }

            $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
            $sql = 'UPDATE `products` SET ' . implode(', ', $setClauses)
                . ' WHERE `id` IN (' . $placeholders . ')';

            DB::statement($sql, array_merge($bindings, $chunkIds));
        }
    }

    protected function deduplicateBatchRows(array $rows): array
    {
        $deduplicated = [];

        foreach ($rows as $row) {
            if (!empty($row['sku'])) {
                $deduplicated['sku:' . $row['sku']] = $row;
            } elseif (!empty($row['link'])) {
                $deduplicated['link:' . $row['link']] = $row;
            } else {
                $deduplicated[] = $row;
            }
        }

        return array_values($deduplicated);
    }

    public function exportToCsv(): void
    {
        $fileName = 'utopya-products.csv';
        $filePath = storage_path('app/public/' . $fileName);
        
        $products = Product::where('shop_url', 'www.utopya.com')->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException('No products found to export');
        }

        $handle = fopen($filePath, 'w');

        // CSV header
        fputcsv($handle, [
            'Product ID', 'EAN Code', 'Title', 'Link', 'Reference',
            'Price', 'Availability'
        ]);

        foreach ($products as $product) {

            
            if (empty($product->stock) || $product->stock <= 0) {
                continue;
            }
            
            fputcsv($handle, [
                $product->sku ?? '',
                $product->ean ?? '',
                $product->title ?? '',
                // $product->description ?? '',
                $product->link ?? '',
                // $product->main_image ?? '',
                // is_array($product->additional_images)
                // ? implode('|', $product->additional_images)
                // : $product->additional_images,
                $product->reference ?? '',
                $product->price ?? '',
                'in stock',
            ]);
        }

        fclose($handle);

        dump('CSV export completed:', $filePath);
    }
}
