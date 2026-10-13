<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class ScreenSurgeryScraper
{
    protected string $baseUrl = 'https://thescreensurgery.com';

    /**
     * Entry point
     */
    protected array $categories = [
      // 'https://thescreensurgery.com/collections/iphone-parts',
      // 'https://thescreensurgery.com/collections/ipad-parts',
      // 'https://thescreensurgery.com/collections/for-apple-watch',
      // 'https://thescreensurgery.com/collections/for-macbook',
      'https://thescreensurgery.com/collections/replacement-screens-and-parts-for-samsung-s26-series',
      'https://thescreensurgery.com/collections/samsung-s25',
      'https://thescreensurgery.com/collections/samsung-s24-series',
      'https://thescreensurgery.com/collections/samsung-s23-series',
      'https://thescreensurgery.com/collections/s22-series',
      'https://thescreensurgery.com/collections/s21-series',
      'https://thescreensurgery.com/collections/samsung-s20-series',
      'https://thescreensurgery.com/collections/samsung-s10-series',
      'https://thescreensurgery.com/collections/samsung-s9-series',
      'https://thescreensurgery.com/collections/samsung-s8-series',
      'https://thescreensurgery.com/collections/samsung-s7-series',
      'https://thescreensurgery.com/collections/samsung-s6-series',
      'https://thescreensurgery.com/collections/samsung-s7-series',
      'https://thescreensurgery.com/collections/samsung-s5-series',
      'https://thescreensurgery.com/collections/samsung-a9-series',
      'https://thescreensurgery.com/collections/samsung-a8-series',
      'https://thescreensurgery.com/collections/samsung-a7-series',
      'https://thescreensurgery.com/collections/samsung-a6-series',
      'https://thescreensurgery.com/collections/samsung-a5-series',
      'https://thescreensurgery.com/collections/samsung-a4-series',
      'https://thescreensurgery.com/collections/samsung-a3-series',
      'https://thescreensurgery.com/collections/samsung-a2-series',
      'https://thescreensurgery.com/collections/samsung-a1-series',
      'https://thescreensurgery.com/collections/samsung-a0-series',
      'https://thescreensurgery.com/collections/samsung-note-20-series',
      'https://thescreensurgery.com/collections/samsung-note-10-series',
      'https://thescreensurgery.com/collections/note-9',
      'https://thescreensurgery.com/collections/note-8',
      'https://thescreensurgery.com/collections/j730',
      'https://thescreensurgery.com/collections/j710',
      'https://thescreensurgery.com/collections/j700',
      'https://thescreensurgery.com/collections/j610',
      'https://thescreensurgery.com/collections/j600',
      'https://thescreensurgery.com/collections/j530',
      'https://thescreensurgery.com/collections/j510',
      'https://thescreensurgery.com/collections/j500',
      'https://thescreensurgery.com/collections/j415',
      'https://thescreensurgery.com/collections/j410',
      'https://thescreensurgery.com/collections/j400',
      'https://thescreensurgery.com/collections/j330',
      'https://thescreensurgery.com/collections/j320',
      'https://thescreensurgery.com/collections/j250',
      'https://thescreensurgery.com/collections/refurbished-parts',
      'https://thescreensurgery.com/collections/ipad-screen-refurbishment',
      'https://thescreensurgery.com/collections/for-apple-watch-glass-oca',
      'https://thescreensurgery.com/collections/for-samsung-refurbishing',
      'https://thescreensurgery.com/collections/nintendo',
      'https://thescreensurgery.com/collections/playstation',
      'https://thescreensurgery.com/collections/microsoft',
      'https://thescreensurgery.com/collections/google-pixel-10-series',
      'https://thescreensurgery.com/collections/google-pixel-9-series-copy',
      'https://thescreensurgery.com/collections/google-pixel-9-series',
      'https://thescreensurgery.com/collections/google-pixel-8-series',
      'https://thescreensurgery.com/collections/google-pixel-7-series',
      'https://thescreensurgery.com/collections/google-pixel-6-series',
      'https://thescreensurgery.com/collections/google-pixel-5-series',
      'https://thescreensurgery.com/collections/google-pixel-4-series',
      'https://thescreensurgery.com/collections/value-lcd',
      'https://thescreensurgery.com/collections/value-soft-oled',
      'https://thescreensurgery.com/collections/tx-pro-screen-replacement-screens-for-iphone',
      'https://thescreensurgery.com/collections/replacement-diagnostic-screens-for-iphone',
      'https://thescreensurgery.com/collections/genuine-pull-iphone-screens',
      'https://thescreensurgery.com/collections/gaming',
      'https://thescreensurgery.com/collections/gp',
      'https://thescreensurgery.com/collections/sony',
      'https://thescreensurgery.com/collections/for-iphone-refurbishing',
      'https://thescreensurgery.com/collections/for-apple-watch-glass-oca',
      'https://thescreensurgery.com/collections/refurbishing-consumables',
      'https://thescreensurgery.com/collections/genuine-pull',
      'https://thescreensurgery.com/collections/stock-clearance',
      'https://thescreensurgery.com/collections/p40-series',
      'https://thescreensurgery.com/collections/p40',
      'https://thescreensurgery.com/collections/p40-lite',
      'https://thescreensurgery.com/collections/huawei-psmart-pro',
      'https://thescreensurgery.com/collections/huawei-psmart',
      'https://thescreensurgery.com/collections/huawei-p30-pro',
      'https://thescreensurgery.com/collections/huawei-p30',
      'https://thescreensurgery.com/collections/huawei-p30-lite',
      'https://thescreensurgery.com/collections/huawei-p20-pro',
      'https://thescreensurgery.com/collections/huawei-p20',
      'https://thescreensurgery.com/collections/huawei-p20-lite',
      'https://thescreensurgery.com/collections/p10-plus',
      'https://thescreensurgery.com/collections/p10',
      'https://thescreensurgery.com/collections/p10-lite',
      'https://thescreensurgery.com/collections/p9-plus',
      'https://thescreensurgery.com/collections/huawei-p9',
      'https://thescreensurgery.com/collections/huawei-mate-20-pro',
      'https://thescreensurgery.com/collections/huawei-mate-20',
      'https://thescreensurgery.com/collections/huawei-mate-20-lite',
      'https://thescreensurgery.com/collections/huawei-mate-10-pro',
      'https://thescreensurgery.com/collections/huawei-mate-10',

    ];
    // public function scrapeAll(): void
    // {
    //     $categories = array_values(array_unique($this->categories));

    //     $totalCategories = count($categories);
    //     $totalExpected = 0;
    //     $totalDiscovered = 0;
    //     $totalSuccessful = 0;
    //     $totalFailed = 0;
    //     $unknownExpectedCategories = 0;

    //     logger()->info('========== SCRAPING STARTED ==========', [
    //         'total_categories' => $totalCategories,
    //     ]);

    //     foreach ($this->categories as $index =>  $categoryUrl) {
            
    //         logger()->info('Processing category', [
    //             'category_number' => $index + 1,
    //             'total_categories' => $totalCategories,
    //             'category_url' => $categoryUrl,
    //         ]);

    //         $result = $this->scrapeCategory($categoryUrl);

    //         if ($result['expected'] !== null) {
    //             $totalExpected += $result['expected'];
    //         } else {
    //             $unknownExpectedCategories++;
    //         }

    //         $totalDiscovered += $result['discovered'];
    //         $totalSuccessful += $result['successful'];
    //         $totalFailed += $result['failed'];

    //     }

    //     logger()->info('========== FINAL SCRAPING SUMMARY ==========', [
    //         'total_categories' => $totalCategories,
    //         'categories_without_expected_count' => $unknownExpectedCategories,
    //         'expected_products' => $unknownExpectedCategories === 0
    //             ? $totalExpected
    //             : 'PARTIAL / UNKNOWN',
    //         'discovered_products' => $totalDiscovered,
    //         'successfully_scraped' => $totalSuccessful,
    //         'failed_products' => $totalFailed,
    //         'missing_products' => $unknownExpectedCategories === 0
    //             ? max(0, $totalExpected - $totalDiscovered)
    //             : 'UNKNOWN',
    //     ]);

    //     $this->exportToCsv();

    //     logger()->info('========== SCRAPING FINISHED ==========');
    // }


    public function scrapeAll(): void
    {
        // Remove duplicate category URLs
        $categories = array_values(array_unique($this->categories));

        
        $totalCategories = count($categories);
        $totalSuccessful = 0;
        $totalFailed = 0;

        logger()->info('========== SCRAPING STARTED ==========', [
            'total_categories' => $totalCategories,
        ]);

        foreach ($categories as $index => $categoryUrl) {
            echo "Category url, {$categoryUrl}\n";
            logger()->info('PROCESSING CATEGORY', [
                'category_number' => $index + 1,
                'total_categories' => $totalCategories,
                'category_url' => $categoryUrl,
            ]);

            $result = $this->scrapeCategory($categoryUrl);

            $totalSuccessful += $result['successful'];
            $totalFailed += $result['failed'];

            logger()->info('CATEGORY COMPLETED', [
                'category_url' => $categoryUrl,
                'products_found' => $result['discovered'],
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'overall_successful' => $totalSuccessful,
                'overall_failed' => $totalFailed,
            ]);
        }

        $this->exportToCsv();

        logger()->info('========== SCRAPING FINISHED ==========', [
            'total_categories' => $totalCategories,
            'total_successful' => $totalSuccessful,
            'total_failed' => $totalFailed,
            'database_products' => Product::where(
                'shop_url',
                'thescreensurgery.com'
            )->count(),
        ]);
    }


    /**
     * STEP 1: Get categories (basic fallback if no sitemap API)
     */
    protected function getCategories(): array
    {
        $html = Http::get($this->baseUrl . '/product-category/')->body();

        $crawler = new Crawler($html);

        // fallback selector (adjust if needed)
        $links = $crawler->filter('a')->each(function ($node) {
            $href = $node->attr('href');

            if (!$href) return null;

            if (Str::contains($href, '/product-category/')) {
                return rtrim($href, '/');
            }

            return null;
        });

        return array_values(array_unique(array_filter($links)));
    }

    // Helper to extra full url
    protected function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }
    /**
     * STEP 2: Scrape category pages with pagination
     */
    protected function scrapeCategory(string $categoryUrl): array
    {
        $page = 1;

        $discoveredCount = 0;
        $successfulCount = 0;
        $failedCount = 0;

        $processedUrls = [];

        logger()->info('========== CATEGORY START ==========', [
            'category_url' => $categoryUrl,
        ]);

        while (true) {

            $url = $categoryUrl . ($page > 1 ? '?page=' . $page : '');

            try {

                logger()->info('SCRAPING CATEGORY PAGE', [
                    'category_url' => $categoryUrl,
                    'page' => $page,
                    'page_url' => $url,
                ]);

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->timeout(30)->get($url);

                if (!$response->successful()) {

                    logger()->error('Category page request failed', [
                        'category_url' => $categoryUrl,
                        'page_url' => $url,
                        'status' => $response->status(),
                    ]);

                    break;
                }

                $crawler = new Crawler($response->body());

                $productLinks = $crawler
                    ->filter('[data-product-item]')
                    ->each(function ($node) {

                        $link = $node->filter('a[data-product-page-link]');

                        if ($link->count() === 0) {
                            return null;
                        }

                        return $link->first()->attr('href');
                    });

                $productLinks = array_values(array_unique(
                    array_filter($productLinks)
                ));

                echo "Categoryssss: {$categoryUrl}\n";
                echo "Page: {$page}\n";
                echo "Products Found: " . count($productLinks) . "\n";

                if (count($productLinks) === 0) {
                    break;
                }

                logger()->info('PRODUCTS FOUND ON PAGE', [
                    'category_url' => $categoryUrl,
                    'page' => $page,
                    'products_found' => count($productLinks),
                ]);

                $newProducts = 0;

                foreach ($productLinks as $index => $link) {

                    $productUrl = $this->normalizeUrl($link);

                    // Skip duplicate products within this category
                    if (isset($processedUrls[$productUrl])) {
                        continue;
                    }

                    $processedUrls[$productUrl] = true;

                    $newProducts++;
                    $discoveredCount++;

                    // Log the product currently being scraped
                    logger()->info('SCRAPING PRODUCT', [
                        'category_url' => $categoryUrl,
                        'product_number' => $discoveredCount,
                        'product_url' => $productUrl,
                    ]);

                    echo "Product URL: " . ($index + 1) . ", {$productUrl}\n";
                    $success = $this->scrapeProduct($productUrl);

                    if ($success) {

                        $successfulCount++;
                        echo "Success Category URL., {$categoryUrl}\n";
                        logger()->info('PRODUCT SCRAPED SUCCESSFULLY', [
                            'product_url' => $productUrl,
                            'category_url' => $categoryUrl,
                            'total_successful' => $successfulCount,
                        ]);

                    } else {

                        $failedCount++;

                        logger()->error('PRODUCT SCRAPING FAILED', [
                            'product_url' => $productUrl,
                            'category_url' => $categoryUrl,
                            'total_failed' => $failedCount,
                        ]);
                    }
                }

                logger()->info('PAGE COMPLETED', [
                    'category_url' => $categoryUrl,
                    'page' => $page,
                    'total_discovered' => $discoveredCount,
                    'successful' => $successfulCount,
                    'failed' => $failedCount,
                ]);

                // Stop if pagination repeats previously processed products
                if ($newProducts === 0) {
                    break;
                }

                $page++;

                usleep(rand(300000, 800000));

            } catch (\Throwable $e) {

                logger()->error('Category scraping error', [
                    'category_url' => $categoryUrl,
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);

                break;
            }
        }

        logger()->info('========== CATEGORY SUMMARY ==========', [
            'category_url' => $categoryUrl,
            'products_found' => $discoveredCount,
            'successful' => $successfulCount,
            'failed' => $failedCount,
        ]);

        return [
            'discovered' => $discoveredCount,
            'successful' => $successfulCount,
            'failed' => $failedCount,
        ];
    }


    protected function getExpectedProductCount(string $categoryUrl): ?int
    {
        $total = 0;
        $page = 1;
        $limit = 250;

        try {

            while (true) {

                $url = rtrim($categoryUrl, '/') . '/products.json';

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept' => 'application/json',
                ])
                    ->timeout(30)
                    ->get($url, [
                        'limit' => $limit,
                        'page' => $page,
                    ]);

                if (!$response->successful()) {

                    logger()->warning('Unable to retrieve expected product count', [
                        'category' => $categoryUrl,
                        'page' => $page,
                        'status_code' => $response->status(),
                    ]);

                    return null;
                }

                $products = $response->json('products');

                if (!is_array($products)) {

                    logger()->warning('Invalid collection JSON response', [
                        'category' => $categoryUrl,
                        'page' => $page,
                    ]);

                    return null;
                }

                $count = count($products);

                $total += $count;

                logger()->info('EXPECTED COUNT PAGE', [
                    'category' => $categoryUrl,
                    'page' => $page,
                    'products_found' => $count,
                    'total_so_far' => $total,
                ]);

                if ($count < $limit) {
                    break;
                }

                $page++;

                // Safety limit to prevent infinite pagination
                if ($page > 100) {

                    logger()->warning('Expected count pagination limit reached', [
                        'category' => $categoryUrl,
                        'count_so_far' => $total,
                    ]);

                    return null;
                }
            }

            logger()->info('EXPECTED PRODUCT COUNT RETRIEVED', [
                'category' => $categoryUrl,
                'expected_products' => $total,
            ]);

            return $total;

        } catch (\Throwable $e) {

            logger()->error('Expected product count request failed', [
                'category' => $categoryUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
    /**
     * STEP 3: Scrape single product page
     */
    protected function scrapeProduct(string $url): bool
    {   
        echo "Scrape Product URL, {$url}\n";

        try {
            // $html = Http::withHeaders([
            //     'User-Agent' => 'Mozilla/5.0 (compatible; LaravelScraper/1.0)'
            // ])->timeout(30)->get($url)->body();
            $maxRetries = 3;
            $response = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {

                // Delay between requests
                sleep(rand(3, 6));

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept' => 'text/html',
                ])
                    ->timeout(30)
                    ->get($url);

                // Rate limit handling
                if ($response->status() === 429) {

                    $retryAfter = $response->header('Retry-After');

                    if (is_numeric($retryAfter)) {
                        $waitSeconds = max(1, min(300, (int) $retryAfter));
                    } elseif ($retryAfter && ($retryTime = strtotime($retryAfter)) !== false) {
                        $waitSeconds = max(1, min(300, $retryTime - time()));
                    } else {
                        $waitSeconds = 60 * $attempt;
                    }

                    echo "RATE LIMITED: {$url}\n";
                    echo "Attempt: {$attempt}/{$maxRetries}\n";

                    if ($attempt < $maxRetries) {
                        echo "Waiting {$waitSeconds} seconds before retry...\n";
                        sleep($waitSeconds);
                    }

                    continue;
                }

                // Other HTTP errors
                if (!$response->successful()) {

                    echo "HTTP ERROR: {$response->status()}\n";
                    echo "Failed URL: {$url}\n";

                    return false;
                }

                // Request successful
                break;
            }

            // Check final response
            if (!$response || !$response->successful()) {

                echo "FAILED AFTER {$maxRetries} ATTEMPTS: {$url}\n";

                logger()->error('Product failed after retries', [
                    'url' => $url,
                    'status' => $response?->status(),
                ]);

                return false;
            }

            $html = $response->body();

            $crawler = new Crawler($html);

            $name = trim($crawler->filter('h1.product-title')->text(''));

            if ($name === '') {

                echo "INVALID PRODUCT HTML: {$url}\n";

                logger()->error('Product name missing', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return false;
            }

            echo "Product Name: {$name}\n";

            // Newer product templates do not render a SKU element.  Do not let
            // an optional field abort an otherwise valid product scrape.
            $skuNode = $crawler->filter('[data-product-sku]');
            $sku = $skuNode->count() > 0 ? trim($skuNode->first()->text('')) : null;

            // -------------------------
            // PRICE
            // -------------------------
            $priceText = $crawler->filter('[data-price]')->text('');
            $price = $this->cleanPrice($priceText);

            $comparePriceText = $crawler->filter('[data-price-compare]')->text('');
            $comparePrice = $this->cleanPrice($comparePriceText);

            // -------------------------
            // SALE PRICE LOGIC
            // -------------------------
            $salePrice = null;
            $regularPrice = $price;

            // Only treat as sale if compare price is valid and higher
            if ($comparePrice && $comparePrice > $price) {
                $salePrice = $price;
                $regularPrice = $comparePrice;
            } else {
                $salePrice = null;
            }

            // -------------------------
            // STOCK (BEST SOURCE)
            // -------------------------
            $stockNode = $crawler->filter('script[data-app="esc-out-of-stock"]');
            $stockJson = $stockNode->count() > 0 ? $stockNode->first()->text('') : '';

            $stockData = json_decode($stockJson, true);

            $available = $stockData[0]['available'] ?? false;
            $inventoryQty = $stockData[0]['inventory_quantity'] ?? null;

            $availability = $available ? 'In Stock' : 'Out of Stock';

            // -------------------------
            // IMAGES
            // -------------------------
            $mainImageNode = $crawler->filter('.product-gallery--viewer img');
            $mainImage = $mainImageNode->count() > 0
                ? $mainImageNode->first()->attr('src')
                : null;

            if ($mainImage && str_starts_with($mainImage, '//')) {
                $mainImage = 'https:' . $mainImage;
            }

            $additionalImages = $crawler->filter('.product-gallery--thumbnail img, .product-gallery--navigation img')
                ->each(function ($node) {
                    $src = $node->attr('src');

                    if ($src && str_starts_with($src, '//')) {
                        $src = 'https:' . $src;
                    }

                    return $src;
                });

            $additionalImages = array_values(array_unique(array_filter($additionalImages)));

            // remove main image from additional
            $additionalImages = array_filter($additionalImages, fn ($img) => $img !== $mainImage);

            // -------------------------
            // UNIQUE ID
            // -------------------------
            $source_id = md5($url);
            
            // The product URL is stable even when the storefront does not expose
            // a SKU. Matching on a nullable SKU made all SKU-less products share
            // one record (and now threw before this point on the new templates).
            Product::updateOrCreate(
                [
                    'shop_url' => 'thescreensurgery.com',
                    'source_id' => $source_id,
                ],
                [
                    'title' => $name,
                    'sku' => $sku,
                    'price' => $regularPrice,
                    'sale_price' => $salePrice,
                    'stock' => $inventoryQty,
                    'main_image' => $mainImage,
                    'additional_images' => array_values($additionalImages),
                    'link' => $url,
                ]
            );
              echo "Product scraped successfully, {$sku}";
            logger()->info('Product scraped successfully', [
                'url' => $url,
                'sku' => $sku,
                'name' => $name,
            ]);
            return true;

        } catch (\Throwable $e) {
            // log and skip broken products
            logger()->error('Product scraping failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clean price text → float
     */
    protected function cleanPrice(?string $priceText): ?float
    {
        if (!$priceText) return null;

        preg_match('/[\d,.]+/', $priceText, $matches);

        return isset($matches[0])
            ? (float) str_replace(',', '', $matches[0])
            : null;
    }

    /**
     * Normalize stock text
     */
    protected function parseStock(?string $stockText): string
    {
        $stockText = strtolower($stockText ?? '');

        if (str_contains($stockText, 'in stock')) {
            return 'In Stock';
        }

        if (str_contains($stockText, 'out')) {
            return 'Out of Stock';
        }

        return 'Unknown';
    }

    public function exportToCsv(): void
    {
        $fileName = 'screensurgery-products.csv';
        $filePath = storage_path('app/public/' . $fileName);

        $file = fopen($filePath, 'w');

        // HEADERS
        fputcsv($file, [
            'sku',
            'title',
            'price',
            'sale_price',
            'availability',
            'stock',
            'link',
        ]);

        // FETCH FROM DB (IMPORTANT PART)
        $query = Product::where('shop_url', 'thescreensurgery.com');

        $query->chunk(500, function ($products) use ($file) {

            foreach ($products as $product) {

                fputcsv($file, [
                    $product->sku,
                    $product->title,
                    $product->price,
                    $product->sale_price,
                    $product->stock > 0 ? 'In Stock' : 'Out of Stock',
                    $product->stock,
                    $product->link,
                ]);
            }

        });

        fclose($file);

        logger()->info("CSV Exported: " . $fileName);
    }
}
