<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Spatie\Browsershot\Browsershot;
use App\Http;

class ReplaceBaseScraper
{
    protected string $apiKey = 'klevu-167059655826815803';
    protected string $endpoint = 'https://eucs31v2.ksearchnet.com/cs/v2/search';
    protected Client $client;
    protected CookieJar $cookies;
    protected array $mainCategories = [
        // 'https://www.replacebase.co.uk/apple-replacement-parts',
        // 'https://www.replacebase.co.uk/samsung-replacement-parts',
        'https://www.replacebase.co.uk/oppo-parts',
        'https://www.replacebase.co.uk/genuine-motorola-parts',
        'https://www.replacebase.co.uk/google-phone-parts',
        'https://www.replacebase.co.uk/mobile-phone-parts',
        'https://www.replacebase.co.uk/smart-watch-parts',
        'https://www.replacebase.co.uk/macbook-parts',
        'https://www.replacebase.co.uk/ipod-parts',
        'https://www.replacebase.co.uk/imac-parts',
        'https://www.replacebase.co.uk/iphone-screen',
        'https://www.replacebase.co.uk/battery',
        'https://www.replacebase.co.uk/tools-equipment',
        'https://www.replacebase.co.uk/all-accessories',
        'https://www.replacebase.co.uk/apple--battery-cover',

        // 'https://www.replacebase.co.uk/iphone-parts',
        // 'https://www.replacebase.co.uk/ipad-parts',
        // 'https://www.replacebase.co.uk/mackbook-parts',

        // 'https://www.replacebase.co.uk/apple-watch',
        // 'https://www.replacebase.co.uk/ipod-watch',
        // 'https://www.replacebase.co.uk/imac-watch',
        // 'https://www.replacebase.co.uk/other-devices',
        'https://www.replacebase.co.uk/apple-mac-mini-parts',
        'https://www.replacebase.co.uk/apple-mac-mini-parts',
        // 'https://www.replacebase.co.uk/samsung--service-pack--phone--screen-assembly'
        'https://www.replacebase.co.uk/samsung-parts-main/',
        // 'https://www.replacebase.co.uk/oppo-parts',
        
        'https://www.replacebase.co.uk/huawei-parts',
        'https://www.replacebase.co.uk/motorola-parts',
        'https://www.replacebase.co.uk/google-phone-parts',
        'https://www.replacebase.co.uk/mobile-phone-parts',
        'https://www.replacebase.co.uk/apple-batteries',
        // add more later
    ];
  protected array $visitedUrls = [];

    public function __construct()
    {
      $this->cookies = new CookieJar();
        $this->client = new Client([
          'timeout' => 240,
          'cookies' => $this->cookies,
          'headers' => [
              'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
              'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
              'Accept-Language' => 'en-GB,en;q=0.5',
              'Referer' => 'https://www.replacebase.co.uk/',
              'Connection' => 'keep-alive',
              'Upgrade-Insecure-Requests' => '1',
          ],
      ]);
    }
    
    protected function randomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/122.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile Safari/605.1.15',
        ];

        return $agents[array_rand($agents)];
    }

    protected function throttle(): void
    {
        // usleep(rand(1500000, 3000000)); // 1.5–3 seconds
        usleep(rand(4_000_000, 8_000_000)); // 4–8 sec

        // Random longer pause
        if (rand(1, 25) === 10) {
            sleep(rand(20, 40));
        }

        // Rare big pause
        if (rand(1, 300) === 50) {
            sleep(rand(120, 240));
        }
    }
    // public function get(string $url): string
    // {
    //     $this->throttle();
    //     $userAgent = $this->randomUserAgent();
    //     try {
            
    //         $browser  = Browsershot::url($url)
    //             ->userAgent($userAgent)
    //             ->noSandbox()
    //             ->addChromiumArguments([
    //                 '--disable-blink-features=AutomationControlled',
    //                 '--disable-dev-shm-usage',
    //                 '--disable-gpu',
    //                 '--no-zygote',
    //             ])
    //             ->waitUntil('domcontentloaded') // waits for JS and AJAX
    //             ->timeout(240);
            
    //         $html = $browser->bodyHtml();

    //         if (
    //             str_contains($html, 'Access denied') ||
    //             str_contains($html, 'Cloudflare') ||
    //             str_contains($html, 'temporarily blocked') ||
    //             str_contains($html, 'Attention Required')
    //         ) {

    //             logger()->warning("Blocked detected on attempt {$attempt} for {$url}");

    //             if ($attempt <= 3) {
    //                 sleep(60 * $attempt); // exponential backoff
    //                 return $this->get($url, $attempt + 1);
    //             }

    //             throw new \Exception("Blocked after multiple attempts");
    //         }

    //         return $html;
    //     } catch (\Throwable $e) {
    //         logger()->error("Request failed: {$url} | Attempt: {$attempt}");

    //         if ($attempt <= 3) {
    //             sleep(30 * $attempt);
    //             return $this->get($url, $attempt + 1);
    //         }

    //         throw $e;
    //     }
    // }


    public function get(string $url): string
    {
        $maxAttempts = 3;
        $attempt = 1;

        while ($attempt <= $maxAttempts) {

            $this->throttle();

            // $proxy = $this->getRandomProxy();
            $userAgent = $this->randomUserAgent();

            try {

                $browser = Browsershot::url($url)
                    ->setChromePath('/usr/bin/google-chrome')
                    ->userAgent($userAgent)
                    ->noSandbox()
                    ->addChromiumArguments([
                        '--disable-blink-features=AutomationControlled',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--no-zygote',
                    ])
                    ->waitUntil('domcontentloaded')
                    ->timeout(240);

                // if ($proxy) {
                //     $browser->setProxyServer($proxy);
                // }

                $html = $browser->bodyHtml();

                if (
                    str_contains($html, 'Access denied') ||
                    str_contains($html, 'Cloudflare') ||
                    str_contains($html, 'Attention Required')
                ) {

                    logger()->warning("Blocked detected (Attempt {$attempt}) for {$url}");
                    sleep(60 * $attempt);
                    $attempt++;
                    continue;
                }

                return $html;

            } catch (\Throwable $e) {

                logger()->error("Request failed (Attempt {$attempt}) for {$url}");

                sleep(30 * $attempt);
                $attempt++;
            }
        }

        throw new \Exception("Failed after {$maxAttempts} attempts: {$url}");
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
            return 'https://www.replacebase.co.uk' . $url;
        }

        return 'https://www.replacebase.co.uk/media/catalog/product/' . ltrim($url, '/');
    }

    public function scrape(): void
    {
        $this->importFromJson();
        $this->exportToCsv();
        // foreach ($this->mainCategories as $mainCategory) {
        //     $this->scrapeCategoryViaApi('Mobile Phone Parts;Apple iPhone Parts;iPhone 16 Pro Parts');
            // $modelCategories = $this->getModelCategories($mainCategory);
            // dd('Starting main category', $modelCategories);
            // if(empty($modelCategories) || $modelCategories === []) {
            //     dump('No model categories found, scraping main category directly', $mainCategory);
            //     $this->scrapeProductListing($mainCategory);
            //     continue;
            // }
            // foreach ($modelCategories as $modelUrl) {
                
            //     $partCategories = $this->getPartCategories($modelUrl);

            //   // 🔥 If no subcategories, scrape the model page itself
            //   if (empty($partCategories) || $partCategories === []) {
            //       dump('No part categories, scraping model directly', $modelUrl);
            //       $this->scrapeProductListing($modelUrl);
                  
            //       continue;
            //   }

            //   foreach ($partCategories as $partUrl) {
            //       $this->scrapeProductListing($partUrl);
            //   }
        //   }
        // }

        // $this->exportToCsv();
    }


    protected function getModelCategories(string $url): array
    {
        dump('Scraping model categories', $url);
        $html = $this->get($url);
        $crawler = new Crawler($html);

        return $crawler
            ->filter('.cms-card-image, a.custom-category-link, a.category-sublisting-image')
            ->each(fn (Crawler $node) => $node->attr('href'));
    }

    protected function getPartCategories(string $modelUrl): array
    {
        dump('Scraping part categories', $modelUrl);

        $html = $this->get($modelUrl);
        $crawler = new Crawler($html);

        $urls = $crawler
            ->filter('a.category-sublisting-title, a.category-sublisting-image')
            ->each(function (Crawler $node) {
                $url = $node->attr('href');
                return $url ? $url : null;
            });

        // remove empty and duplicates
        return array_values(array_unique(array_filter($urls)));
    }

    protected function scrapeCategoryViaApi(string $categoryPath): void
    {
        dump("Scraping category via API", $categoryPath);

        $limit = 100;
        $offset = 0;
        $allProducts = [];

        while (true) {

            $payload = [
                "context" => [
                    "apiKeys" => ["klevu-167059655826815803"]
                ],
                "recordQueries" => [
                    [
                        "id" => "productSearch",
                        "typeOfRequest" => "SEARCH",
                        "settings" => [
                            "query" => [
                                "term" => "*",
                            ],
                            "typeOfRecords" => ["KLEVU_PRODUCT"],
                            "limit" => $limit,
                            "offset" => $offset
                        ]
                    ]
                ]
            ];

            $response = $this->client->post(
                'https://eucs31v2.ksearchnet.com/cs/v2/search',
                ['json' => $payload]
            );

            $data = json_decode($response->getBody(), true);

            $records = $data['queryResults'][0]['records'] ?? [];

            if (empty($records)) {
                break;
            }
            foreach ($records as $product) {
                $allProducts[] = $product;
            }
            // foreach ($records as $product) {

            //     $productUrl = $product['url'] ?? null;

            //     if (!$productUrl || in_array($productUrl, $this->visitedUrls)) {
            //         continue;
            //     }

            //     $this->visitedUrls[] = $productUrl;

            //     $this->scrapeProduct($productUrl);

            //     // usleep(300000);
            //     usleep(rand(2_000_000, 5_000_000));
            // }

            // if (count($records) < $limit) {
            //     break; // last page
            // }

            // $offset += $limit;
            dump("Fetched batch. Offset: {$offset}");

            if (count($records) < $limit) {
                break;
            }

            $offset += $limit;

            usleep(rand(500000, 1000000));
        }

        $total = count($allProducts);

        dump("TOTAL PRODUCTS FOUND: {$total}");

        file_put_contents(
            storage_path('app/klevu_products_test.json'),
            json_encode($allProducts, JSON_PRETTY_PRINT)
        );

        dump("File saved to storage/app/klevu_products_test.json");
    }

    protected function extractCategoryPath(string $html): ?string
    {
        $crawler = new Crawler($html);

        if (!$crawler->filter('.kuResultsHeadingTitleContainer span')->count()) {
            return null;
        }

        $breadcrumbText = trim(
            $crawler->filter('.kuResultsHeadingTitleContainer span')->text()
        );


        $parts = array_map('trim', explode('>', $breadcrumbText));

        return implode(';', $parts);
    }

    protected function scrapeProductListing(string $url): void
    {
        dump('Scraping listing', $url);
        $html = $this->get($url);
        $crawler = new Crawler($html);

        $categoryPath = $this->extractCategoryPath($html);

        if ($categoryPath) {
            dump('Detected Klevu category path:', $categoryPath);
            $this->scrapeCategoryViaApi($categoryPath);
            return; // IMPORTANT
        }
        // $products = $crawler->filter('li.klevuProduct, li.item.product-item');

        // if ($products->count() === 0) {
        //     dump('No products found on page');
        //     return;
        // }
        // dump('Found products', $products->count());
    //   $products->each(function (Crawler $node) {

    //       $productUrl = $node
    //           ->filter('a.klevuProductClick, a.product.photo.product-item-photo')
    //           ->first()
    //           ->attr('href');

    //       if (!$productUrl) {
    //           return;
    //       }

    //       try {
    //           $this->scrapeProduct($productUrl);
    //       } catch (\Throwable $e) {
    //           logger()->error('ReplaceBase product failed', [
    //               'url' => $productUrl,
    //               'error' => $e->getMessage(),
    //           ]);
    //       }

    //       // human delay
    //       usleep(400_000);
    //   });
    }

    protected function scrapeProduct(string $productUrl): void
    {
        dump('Scraping product', $productUrl);
        $html = $this->get($productUrl);
        $crawler = new Crawler($html);

        $name = $crawler->filter('h1.page-title span')->count()
            ? trim($crawler->filter('h1.page-title span')->text())
            : null;

        $sku = $crawler->filter('.product.attribute.sku .value')->count()
            ? trim($crawler->filter('.product.attribute.sku .value')->text())
            : null;

        // PRICE (ex VAT)
        $priceExVat = null;

        if ($crawler->filter('.price-excluding-tax .price')->count()) {
            $priceText = $crawler->filter('.price-excluding-tax .price')->text();
            $priceExVat = (float) preg_replace('/[^0-9.]/', '', $priceText);
        }

        $availability = 'unknown';
        $quantity = null;

        // OUT OF STOCK
        if ($crawler->filter('.product-availability-out-of-stock')->count()) {
            $availability = 'out_of_stock';
            $quantity = 0;
        }

        // LIMITED STOCK (e.g. "2 left")
        elseif ($crawler->filter('.availability.only')->count()) {
            $availability = 'in_stock';

            $qtyText = $crawler->filter('.availability.only strong')->text('');
            $quantity = is_numeric($qtyText) ? (int) $qtyText : null;
        }

        // IN STOCK (no qty)
        elseif ($crawler->filter('.product-availability-in-stock')->count()) {
            $availability = 'in_stock';
        }

        // IMAGES
        $images = $crawler->filter('.fotorama__stage__frame')
            ->each(fn (Crawler $img) =>
                $this->normalizeUrl($img->attr('href'))
            );
    //     $image = $crawler->filter('.fotorama__stage__frame img.fotorama__img')->count()
    // ? $crawler->filter('.fotorama__stage__frame img.fotorama__img')->attr('src')
    // : null;
        $images = array_values(array_unique(array_filter($images)));

        $mainImage = $images[0] ?? null;
        $additionalImages = [];

        // DESCRIPTION
        $description = null;

        if ($crawler->filter('.product.attribute.description .value')->count()) {
            $descriptionNode = $crawler->filter('.product.attribute.description .value');

            $description = $descriptionNode->filter('div')->count()
                ? trim($descriptionNode->filter('div')->html())
                : trim($descriptionNode->html());
        }

        // MORE INFO (REAL STRUCTURE)
        $moreInfo = [];
        $mpn = null;

        $crawler->filter('.additional-attributes .row')->each(
            function (Crawler $row) use (&$moreInfo, &$mpn) {
                $key = trim(rtrim($row->filter('.label')->text(''), ':'));
                $value = trim($row->filter('.data')->text(''));

                if ($key && $value) {
                    $moreInfo[$key] = $value;

                    if (str_contains(strtolower($key), 'Manufacturers Part No')) {
                        $mpn = $value;
                    }
                }
            }
        );

        $payload = [
            'sku' => $sku,
            'title' => $name,
            'reference' => $mpn,
            'price' => $priceExVat,
            'availability' => $availability,
            'stock' => $quantity,
            // 'description' => $description,
            'link' => $productUrl,
            'shop_url' => 'www.replacebase.co.uk',
            'main_image' => $mainImage,
            'additional_images' => $additionalImages,
            'more_information' => $moreInfo,
        ];
        dump('Scraped product data', $payload);
        $this->storeProduct($payload);
    
    }

    public function importFromJson(): void
    {
        $path = storage_path('app/klevu_products_test.json');

        if (!file_exists($path)) {
            dump("JSON file not found.");
            return;
        }

        $json = file_get_contents($path);
        $products = json_decode($json, true);

        if (empty($products)) {
            dump("No products found in JSON.");
            return;
        }

        dump("Importing " . count($products) . " products...");

        foreach ($products as $product) {

            $sku = $product['sku'] ?? null;
            if (!$sku) {
                continue;
            }

            $payload = [
                'sku' => $sku,
                'title' => $product['name'] ?? '',
                'price' => (float) ($product['price'] ?? 0),
                'sale_price' => (float) ($product['salePrice'] ?? $product['price'] ?? 0),
                'description' => $product['shortDesc'] ?? null,
                'main_image' => $product['image'] ?? null,
                'additional_images' => [],
                'shop_url' => 'www.replacebase.co.uk',
                'link' => $product['url'] ?? null,
                'reference' => $product['mpn'] ?? null,
                'stock' => ($product['inStock'] ?? 'no') === 'yes' ? 5 : 0,
                'variant_id' => null,
                'colour' => null,
                'size' => null,
                'raw' => $product,
            ];

            $this->storeProduct($payload);
        }

        dump("Import completed.");
    }

    /**
     * STEP 5: Store/update by SKU
     */
    protected function storeProduct(array $data): void
    {
        $product = Product::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );
        
        // $payload = [
        //     'sku' => $data['sku'],
        //     'title' => $data['title'],
        //     'price' => $data['price'],
        //     // 'price_inc_vat' => $priceIncVat,
        //     'description' => null,
        //     'main_image' => $data['main_image'],
        //     'additional_images' => $data['additional_images'],
        //     'availability' => $data['availability'],
        //     'shop_url' => $data['shop_url'],
        //     'link' => $data['link'],
        //     'sale_price' => $data['price'],
        //     'reference' => $data['reference'],
        //     'colour' => null,
        //     'size' => null,
        //     'stock' => $data['availability'] === 'in_stock'
        //         ? ($data['stock'] ?? 2)
        //         : 0,
        //     'variant_id' => null,
        //     'raw' => $data,
        // ];

        // $query = Product::query();
        // // if (Product::where('sku', $data['sku'])->exists()) {
        // //     echo "Duplicate SKU detected: {$data['sku']}";
        // // }
        // if (!empty($payload['sku'])) {
        //     $query->where('sku', $payload['sku']);
        // }
        
        // $product = $query->first();
        // if ($product) {
        //     $product->update($payload);
        // } else {
        //     $product = Product::create($payload);
        // }

        // if (!empty($data['more_information']) && is_array($data['more_information'])) {

        //     // DELETE old attributes (clean sync)
        //     Attribute::where('product_id', $product->id)->delete();

        //     // Insert new attributes
        //     foreach ($data['more_information'] as $key => $value) {
        //         Attribute::create([
        //             'product_id' => $product->id,
        //             'key'        => trim($key),
        //             'value'      => trim($value),
        //         ]);
        //     }
        // }
    }

    public function exportToCsv(): void
    {
        $fileName = 'replacebase-products.csv';
        $filePath = storage_path('app/public/' . $fileName);
        
        $products = Product::where('shop_url', 'www.replacebase.co.uk')->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException('No products found to export');
        }

        $handle = fopen($filePath, 'w');

        // CSV header
        fputcsv($handle, [
            'Product ID','Title', 'Link', 'Reference', 'Price', 'Stock', 'Availability'
        ]);

        foreach ($products as $product) {

            
            if (empty($product->stock) || $product->stock <= 0) {
                continue;
            }
            
            fputcsv($handle, [
                $product->sku ?? '',
                $product->title ?? '',
                // $product->description ?? '',
                $product->link ?? '',
                // $product->main_image ?? '',
                // is_array($product->additional_images)
                // ? implode('|', $product->additional_images)
                // : $product->additional_images,
                $product->reference ?? '',
                $product->price ?? '',
                $product->stock ?? '',
                'in stock',
            ]);
        }

        fclose($handle);

        dump('CSV export completed:', $filePath);
    }
}
