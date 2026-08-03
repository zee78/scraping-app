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
      'https://thescreensurgery.com/collections/iphone-parts',
      'https://thescreensurgery.com/collections/ipad-parts',
      'https://thescreensurgery.com/collections/for-apple-watch',
      'https://thescreensurgery.com/collections/for-macbook',
      'https://thescreensurgery.com/collections/samsung-s25',
      'https://thescreensurgery.com/collections/samsung-s24-series',
      'https://thescreensurgery.com/collections/samsung-s23-series',
      'https://thescreensurgery.com/collections/samsung-s22-series',
      'https://thescreensurgery.com/collections/samsung-s21-series',
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
      'https://thescreensurgery.com/collections/samsung-note-9',
      'https://thescreensurgery.com/collections/samsung-note-8',
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
    public function scrapeAll(): void
    {
        // $categories = $this->getCategories();
        // dd($categories); // Debug: inspect category URLs before scraping
        foreach ($this->categories as $categoryUrl) {
            $this->scrapeCategory($categoryUrl);
        }
        $this->exportToCsv();
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
    // protected function scrapeCategory(string $categoryUrl): void
    // {
    //     $page = 1;

    //     while (true) {

    //         $url = $page === 1
    //             ? $categoryUrl
    //             : $categoryUrl . '/page/' . $page;

    //         $html = Http::withHeaders([
    //             'User-Agent' => 'Mozilla/5.0 (compatible; LaravelScraper/1.0)'
    //         ])->timeout(30)->get($url)->body();

    //         $crawler = new Crawler($html);

    //         // product links (WooCommerce default)
    //         $productLinks = $crawler->filter('a.productitem--image-link')
    //             ->each(fn ($node) => $node->attr('href'));

    //         $productLinks = array_filter($productLinks);

    //         if (empty($productLinks)) {
    //             break; // stop pagination
    //         }

    //         foreach ($productLinks as $link) {
    //             if (!$link) continue;

    //             $link = $this->normalizeUrl($link); 
    //             $this->scrapeProduct($link);
    //         }

    //         $page++;

    //         usleep(rand(300000, 800000)); // delay
    //     }
    // }

    protected function scrapeCategory(string $categoryUrl): void
    {
        $page = 1;

        while (true) {

            $url = $categoryUrl . ($page > 1 ? '?page=' . $page : '');
            $html = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0'
            ])->timeout(30)->get($url)->body();

            $crawler = new Crawler($html);

            $productLinks = $crawler->filter('a.productitem--image-link')
                ->each(fn ($node) => $node->attr('href'));

            $productLinks = array_filter($productLinks);

            dump("Page: $page | Products: " . count($productLinks));

            if (count($productLinks) === 0) {
                break;
            }

            foreach ($productLinks as $link) {
                $this->scrapeProduct($this->normalizeUrl($link));
            }

            $page++;

            if ($page > 50) break; // safety

            usleep(rand(300000, 800000));
        }
    }

    /**
     * STEP 3: Scrape single product page
     */
    protected function scrapeProduct(string $url): void
    {

        $html = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; LaravelScraper/1.0)'
        ])->timeout(30)->get($url)->body();

        $crawler = new Crawler($html);

        try {
            // -------------------------
            // BASIC FIELDS
            // -------------------------
            $name = $crawler->filter('h1.product-title')->text('');

            $sku = $crawler->filter('[data-product-sku]')->text('');

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
            $stockJson = $crawler->filter('script[data-app="esc-out-of-stock"]')->text('');

            $stockData = json_decode($stockJson, true);

            $available = $stockData[0]['available'] ?? false;
            $inventoryQty = $stockData[0]['inventory_quantity'] ?? null;

            $availability = $available ? 'In Stock' : 'Out of Stock';

            // -------------------------
            // IMAGES
            // -------------------------
            $mainImage = $crawler->filter('.product-gallery--viewer img')->attr('src');

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
            
            // $data = [
            //     'shop_url' => 'thescreensurgery.com',
            //     'source_id' => $source_id,
            //     'name' => $name,
            //     'sku' => $sku ?: null,
            //     'price' => $regularPrice,
            //     'sale_price' => $salePrice,
            //     // 'availability' => $availability,
            //     'stock' => $inventoryQty,
            //     'main_image' => $mainImage,
            //     'additional_images' => json_encode($additionalImages),
            //     'link' => $url,
            // ];
            // STORE (adjust model as needed)
              Product::updateOrCreate(
                [  
                  'sku' => $sku ?: null,
                ],
                [
                  'shop_url' => 'thescreensurgery.com',
                  'title' => $name,
                  'sku' => $sku ?: null,
                  'price' => $regularPrice,
                  'sale_price' => $salePrice,
                  'availability' => $availability,
                  'stock' => $inventoryQty,
                  'main_image' => $mainImage,
                  'additional_images' => json_encode($additionalImages),
                  'link' => $url,
                ]
            );

        } catch (\Throwable $e) {
            // log and skip broken products
            logger()->error('Scrape failed: ' . $url, [
                'error' => $e->getMessage()
            ]);
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
