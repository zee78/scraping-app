<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;

class MobileSentrixScraper
{
    protected Client $client;
    protected CookieJar $cookies;
    protected int $productConcurrency = 15;
    protected int $requestDelayMinUs = 20000;
    protected int $requestDelayMaxUs = 80000;
    protected array $categories = [
        'https://www.mobilesentrix.co.uk/replacement-parts/apple/iphone-parts/'

      // Screens
      // 'https://www.mobilesentrix.co.uk/refurbishing/google-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/iphone-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/refurbish-ipad-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/iwatch-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/samsung-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/motorola-parts',
      // 'https://www.mobilesentrix.co.uk/refurbishing/lenovo-parts',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/apple',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/samsung',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/google-pixel',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/g-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/razr-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/e-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/one-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/droid-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/z-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/x-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/watch-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/edge-series',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/motorola/motorola-others',

      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/amazon',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/alcatel',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/asus-zenfone',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/att',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/acer',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/blackberry',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/blu',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/boost',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/cat',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/coolpad',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/cricket',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/essential',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/fitbit-watches',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/gopro',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/lg',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/hp',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/honor-parts',
      // 'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/htc',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/huawei',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/infinix',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/insta360',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/kyocera-duraforce-parts',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/nokia',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/lenovo',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/nexus',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/microsoft-surface',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/nothing',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/oneplus',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/oppo',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/oukitel',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/realme',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/revvl',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/sony-xperia',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/tcl',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/tecno',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/t-phone',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/vivo-parts',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/zebra',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/xiaomi',
      //   'https://www.mobilesentrix.co.uk/replacement-parts/others-brands-parts/zte',


      // 'https://www.mobilesentrix.co.uk/board-components/samsung',
      // 'https://www.mobilesentrix.co.uk/board-components/google',
      // 'https://www.mobilesentrix.co.uk/board-components/boost-mobile',
      // 'https://www.mobilesentrix.co.uk/board-components/lg',
      // 'https://www.mobilesentrix.co.uk/board-components/microsoft',
      // 'https://www.mobilesentrix.co.uk/board-components/motorola',
      // 'https://www.mobilesentrix.co.uk/board-components/revvl',
      // 'https://www.mobilesentrix.co.uk/board-components/tcl',
      // 'https://www.mobilesentrix.co.uk/board-components/mac-mini',
    
      // 'https://www.mobilesentrix.co.uk/board-components/ipad',
      // 'https://www.mobilesentrix.co.uk/board-components/macbook',
      // 'https://www.mobilesentrix.co.uk/board-components/macbook/macbook-pro',
      // 'https://www.mobilesentrix.co.uk/board-components/macbook/macbook',
      // 'https://www.mobilesentrix.co.uk/board-components/macbook/macbook-air',
      // 'https://www.mobilesentrix.co.uk/board-components/motorola',


      // 'https://www.mobilesentrix.co.uk/repair-tools/essentials',
      // 'https://www.mobilesentrix.co.uk/repair-tools/oem-service-tools',
      // 'https://www.mobilesentrix.co.uk/repair-tools/supplies',
      // 'https://www.mobilesentrix.co.uk/repair-tools/back-glass-repair',
      // 'https://www.mobilesentrix.co.uk/repair-tools/testing-devices',
      // 'https://www.mobilesentrix.co.uk/repair-tools/microsoldering',
      // 'https://www.mobilesentrix.co.uk/repair-tools/other-soldering-parts',
      // 'https://www.mobilesentrix.co.uk/repair-tools/refurbishing-tools/',
      // 'https://www.mobilesentrix.co.uk/repair-tools/camera-repair',
      // 'https://www.mobilesentrix.co.uk/repair-tools/universal-components',
      // 'https://www.mobilesentrix.co.uk/repair-tools/hardware-programmers',
      // 'https://www.mobilesentrix.co.uk/repair-tools/organization',
      // 'https://www.mobilesentrix.co.uk/accessories',
    //   'https://www.mobilesentrix.co.uk/accessories/casper-tempered-glass',
    //   'https://www.mobilesentrix.co.uk/accessories/all-oem-pull',
    //   'https://www.mobilesentrix.co.uk/accessories/cables',
    //   'https://www.mobilesentrix.co.uk/accessories/generic',
    //   'https://www.mobilesentrix.co.uk/accessories/game-console-accessories',
    //   'https://www.mobilesentrix.co.uk/accessories/hard-drives',
    //   'https://www.mobilesentrix.co.uk/accessories/skins',
      // 'https://www.mobilesentrix.co.uk/game-console-parts',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/microsoft',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/nintendo',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/sony',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/oculus',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/valve',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/asus',
    //   'https://www.mobilesentrix.co.uk/game-console-parts/board-component'
    
    ];
    protected array $visitedUrls = [];

    public function __construct()
    {
        $this->cookies = new CookieJar();

        $configuredConcurrency = (int) config('services.mobilesentrix.concurrency', 8);
        $this->productConcurrency = max(1, $configuredConcurrency);

        $configuredMinDelay = (int) config('services.mobilesentrix.request_delay_min_us', 50000);
        $configuredMaxDelay = (int) config('services.mobilesentrix.request_delay_max_us', 150000);
        $this->requestDelayMinUs = max(0, min($configuredMinDelay, $configuredMaxDelay));
        $this->requestDelayMaxUs = max($this->requestDelayMinUs, max($configuredMinDelay, $configuredMaxDelay));

        $this->client = new Client([
            'base_uri' => 'https://www.mobilesentrix.co.uk',
            'cookies'  => $this->cookies,
            'timeout'  => 60,
            'headers'  => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept'     => 'text/html,application/xhtml+xml',
            ],
        ]);
    }
  
    protected function getLoginTokens(): array
    {
        $response = $this->client->get('/customer/account/login/');
        $html = (string) $response->getBody();

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);

        return [
            'form_key' => $crawler->filter('input[name="form_key"]')->attr('value'),
            'tkn'      => $crawler->filter('input[name="tkn"]')->attr('value'),
            'ck4'      => $crawler->filter('input[name="ck4"]')->attr('value'),
            'ck4rom'   => $crawler->filter('input[name="ck4rom"]')->attr('value'),
        ];
    }
  
    public function login(): void
    {
      $tokens = $this->getLoginTokens();

      $response = $this->client->post(
          '/customer/account/checkAuthVerify/',
          [
              'form_params' => [
                  'useremail'     => base64_encode(config('services.mobilesentrix.email')),
                  'userpassword'  => base64_encode(config('services.mobilesentrix.password')),
                  'form_key'      => $tokens['form_key'],
                  'tkn'           => $tokens['tkn'],
                  'ck4'           => $tokens['ck4'],
                  'ck4rom'        => $tokens['ck4rom'],
                  'ishomepage'    => '1',
                  'referer'       => '',
              ],
              'headers' => [
                  'Referer' => 'https://www.mobilesentrix.co.uk/customer/account/login/',
                  'Origin'  => 'https://www.mobilesentrix.co.uk',
              ],
              'allow_redirects' => true,
          ]
      );

      $body = (string) $response->getBody();
      // dd($body);
      if (str_contains($body, 'Login or create an account')) {
          throw new \Exception('MobileSentrix login failed');
      }
    }

    protected function getCsrfToken(): string
    {
        $response = $this->client->get('/customer/account/login/');
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);

        return $crawler
            ->filter('input[name="form_key"]')
            ->attr('value');
    }
    protected function throttle(): void
    {
        if ($this->requestDelayMaxUs <= 0) {
            return;
        }

        usleep(random_int($this->requestDelayMinUs, $this->requestDelayMaxUs));
    }
    public function get(string $url, bool $shouldThrottle = true): string
    {
        if ($shouldThrottle) {
            $this->throttle();
        }

        $response = $this->client->get($url, [
            'cookies' => $this->cookies,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ],
        ]);

        return (string) $response->getBody();
    }

    public function scrapeCategories(): void
    {
        foreach ($this->categories as $categoryUrl) {
            $this->scrapeCategoryTree($categoryUrl);
        }

        $this->exportCsv('mobilesentrix_products.csv');
    }

    public function crawl(): void
    {
        // Backward-compatible command entrypoint.
        $this->scrapeCategories();
    }

  protected function scrapeCategoryTree(string $url): void
  {
    $url = rtrim($url, '/');

    // 1. Prevent infinite loops
    if (isset($this->visitedUrls[$url])) {
        return;
    }

    $this->visitedUrls[$url] = true;

    echo "Visiting: {$url}\n";

    $html = $this->get($url);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($html);

    // 2. If real products exist → scrape and STOP
    // if ($this->pageHasProducts($html)) {
    //     echo "  → Final product category detected\n";
    //     $this->scrapeCategoryProducts($url);
    //     return;
    // }
    if ($crawler->filter('li.item')->count() > 0) {
        echo "  → AJAX product category detected\n";
        $this->scrapeCategoryProducts($url);
        return;
    }

    echo "  → Discovering sub-categories\n";

    // 3. Extract ONLY valid deeper sub-categories
    $subCategoryLinks = array_unique(
        $crawler->filter('a[href]')
            ->each(function ($node) use ($url) {
                $href = rtrim($node->attr('href'), '/');

                if (!$href) {
                    return null;
                }

                // Must be refurbishing
                if (!str_contains($href, '/refurbishing/')) {
                    return null;
                }

                // Ignore self-link
                if ($href === $url) {
                    return null;
                }

                // Only allow deeper URLs
                if (substr_count($href, '/') <= substr_count($url, '/')) {
                    return null;
                }

                return $href;
            })
    );

    $subCategoryLinks = array_filter($subCategoryLinks);

    foreach ($subCategoryLinks as $subUrl) {
        $this->scrapeCategoryTree($subUrl);
        usleep(300000); // throttle
    }
  }

  protected function pageHasProducts(Crawler $crawler): bool
  {
      // return preg_match(
      //     '#/refurbishing/.+/.+/.+#',
      //     $html
      // ) === 1;
      return $crawler->filter('ul.products-grid li.item')->count() > 0;
  }

  protected function scrapeCategoryProducts(string $categoryUrl): void
  {
    $page = 1;
    $totalPages = null;

    do {
        $url = $categoryUrl . '?p=' . $page;

        echo "  AJAX page {$page}: {$url}\n";

        $response = $this->client->get($url, [
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ],
        ]);

        $json = json_decode((string) $response->getBody(), true);

        if (
            !$json ||
            empty($json['listing'])
        ) {
            echo "  ❌ No listing HTML returned\n";
            break;
        }

        // Capture total pages ONCE
        if ($totalPages === null && !empty($json['total_pages'])) {
            $totalPages = (int) $json['total_pages'];
            echo "  📄 Total pages: {$totalPages}\n";
        }

        $crawler = new Crawler($json['listing']);

        $productLinks = array_values(array_filter(array_unique(
            $crawler
                ->filter('li.item a.product-image')
                ->each(fn ($node) => $node->attr('href'))
        )));

        if (empty($productLinks)) {
            echo "  ⚠ No products on page {$page}\n";
            break;
        }

        $this->scrapeProductsConcurrently($productLinks);

        $page++;
        $this->throttle();

    } while ($totalPages === null || $page <= $totalPages);

  }

  protected function scrapeProductsConcurrently(array $productLinks): void
  {
    $rows = [];

    $requests = function () use ($productLinks) {
        foreach ($productLinks as $productUrl) {
            yield function () use ($productUrl) {
                return $this->client->getAsync($productUrl, [
                    'cookies' => $this->cookies,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    ],
                ]);
            };
        }
    };

    $pool = new Pool($this->client, $requests(), [
        'concurrency' => $this->productConcurrency,
        'fulfilled' => function ($response, $index) use (&$rows, $productLinks) {
            $productUrl = $productLinks[$index] ?? null;

            if (!$productUrl) {
                return;
            }

            try {
                $payload = $this->buildProductPayload((string) $response->getBody(), $productUrl);

                if ($payload !== null) {
                    $rows[] = $payload;
                }
            } catch (\Throwable $e) {
                logger()->error("Product parse failed: {$productUrl}", [
                    'error' => $e->getMessage(),
                ]);
            }
        },
        'rejected' => function ($reason, $index) use ($productLinks) {
            $productUrl = $productLinks[$index] ?? 'unknown';

            logger()->error("Product request failed: {$productUrl}", [
                'error' => (string) $reason,
            ]);
        },
    ]);

    $pool->promise()->wait();
    $this->upsertProductBatch($rows);
  }



    public function scrapeProduct(string $productUrl): void
  {
        echo "      Scraping product: {$productUrl}\n";
        $html = $this->get($productUrl, false);
        $payload = $this->buildProductPayload($html, $productUrl);

        if ($payload !== null) {
                $this->upsertProductBatch([$payload]);
        }
    }

    protected function buildProductPayload(string $html, string $productUrl): ?array
    {
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);

    // Product Name
    $name = trim(
    $crawler->filter('.product-name h1 .copy-text')->count()
        ? $crawler->filter('.product-name h1 .copy-text')->first()->text()
        : ''
    );


      // SKU
        //   $sku = trim(
        //       $crawler
        //           ->filter('.skudisplayweb .copy-text')
        //           ->first()
        //           ->text('')
        //   );
        $sku = trim(
            $crawler->filter('.skudisplayweb .copy-text')->count()
                ? $crawler->filter('.skudisplayweb .copy-text')->first()->text() : ''
        );

        // MPN
        $mpn = trim(
            $crawler->filter('.skudisplayweb .mpn-number-text .copy-text')->count()
                ? $crawler->filter('.skudisplayweb .mpn-number-text .copy-text')->first()->text() : ''
        );

          // Prices
        //   $priceExVat = trim(
        //       $crawler
        //           ->filter('.price-box .regular-price .price')
        //           ->first()
        //           ->text('')
        //   );
        $priceExVat = trim(
            $crawler->filter('.price-box .regular-price .price')->count()
                ? $crawler->filter('.price-box .regular-price .price')->first()->text()
                : ''
        );

        //   $priceIncVat = trim(
        //       $crawler
        //           ->filter('.price-box .old-price .price')
        //           ->first()
        //           ->text('')
        //   );
        $priceIncVat = trim(
              $crawler
                  ->filter('.price-box .old-price .price')->count()
                  ? $crawler->filter('.price-box .old-price .price')->first()->text()
                  : ''
          );

      // Description (bullet points)
    //   $description = $crawler
    //       ->filter('#product_tabs_description_tabbed_contents .std ul.description li')
    //       ->each(fn ($node) => trim($node->text()));
        $description = null;

        // Case 1: Bullet list description
        if ($crawler->filter('#product_tabs_description_tabbed_contents .std ul.description li')->count()) {
            $description = $crawler
                ->filter('#product_tabs_description_tabbed_contents .std ul.description li')
                ->each(fn ($node) => trim($node->text()));
        }

        // Case 2: Plain text description
        elseif ($crawler->filter('#product_tabs_description_tabbed_contents .std')->count()) {
            $description = trim(
                $crawler
                    ->filter('#product_tabs_description_tabbed_contents .std')
                    ->text('')
            );
        }

        // Main image
        $mainImage = null;

        if ($crawler->filter('.MagicZoom')->count()) {
            $mainImage = $this->normalizeUrl(
                $crawler->filter('.MagicZoom')->first()->attr('href')
            );
        }
        // Images (FULL size)
        $images = [];

        // if ($crawler->filter('.MagicToolboxSelectorsContainer a')->count()) {
        //     $images = array_values(array_unique(array_filter(
        //         $crawler
        //             ->filter('.MagicToolboxSelectorsContainer a img')
        //             ->each(fn ($node) => $this->normalizeUrl($node->attr('src')))
        //     )));
        // }

        if ($crawler->filter('.MagicToolboxSelectorsContainer a')->count()) {
            $images = array_values(array_unique(array_filter(
                $crawler
                    ->filter('.MagicToolboxSelectorsContainer a img')
                    ->each(function ($node) {
                        $href = trim($node->attr('src'));

                        if (!$href) {
                            return null;
                        }

                        return $this->normalizeUrl($href);
                    })
            )));
        }

        // Clean prices
        $priceExVat = number_format((float) preg_replace('/[^0-9.]/', '', $priceExVat), 2, '.', '');
        $priceIncVat = number_format((float) preg_replace('/[^0-9.]/', '', $priceIncVat), 2, '.', '');

        // Availability
        $availability = 'out_of_stock';

        if ($crawler->filter('.add-to-cart button.btn-cart')->count()) {
            $button = $crawler->filter('.add-to-cart button.btn-cart')->first();

            $title = strtolower($button->attr('title') ?? '');
            $class = strtolower($button->attr('class') ?? '');
            $text  = strtolower(trim($button->text()));

            if (
                str_contains($title, 'pre-order') ||
                str_contains($class, 'btn-preorder') ||
                str_contains($text, 'pre-order')
            ) {
                $availability = 'out_of_stock';
            } elseif (
                str_contains($title, 'add to cart') ||
                str_contains($text, 'add to cart')
            ) {
                $availability = 'in_stock';
            }
        }

        $payload = [
            'sku' => $sku,
            'title' => $name,
            'price' => $priceExVat,
            // 'price_inc_vat' => $priceIncVat,
            'description' => is_array($description) ? json_encode($description) : $description,
            'main_image' => $mainImage,
            'additional_images' => $images,
            'shop_url' => 'www.mobilesentrix.co.uk',
            'link' => $productUrl,
            'sale_price' => $priceIncVat,
            'reference' => $mpn,
            'colour' => null,
            'size' => null,
            'stock' => $availability === 'in_stock' ? 10 : 0,
            'variant_id' => null,
            'raw' => null,
        ];

        if (empty($payload['sku'])) {
            logger()->warning("Skipping MobileSentrix product without SKU", [
                'url' => $productUrl,
            ]);

            return null;
        }

        return $payload;
  }

  protected function upsertProductBatch(array $rows): void
  {
    if (empty($rows)) {
        return;
    }

    // 1. Deduplicate in-memory so the same SKU scraped twice in one page never produces two inserts.
    $rows = $this->deduplicateBatchRows($rows);

    $now = now()->toDateTimeString();

    foreach ($rows as &$row) {
        if (isset($row['additional_images']) && is_array($row['additional_images'])) {
            $row['additional_images'] = json_encode(array_values($row['additional_images']));
        }

        if (array_key_exists('raw', $row) && is_array($row['raw'])) {
            $row['raw'] = json_encode($row['raw']);
        }

        $row['created_at'] = $now;
        $row['updated_at'] = $now;
    }
    unset($row);

    // 2. ONE SELECT to find which rows already exist (sku + shop_url).
    //    This avoids N separate SELECT queries inside updateOrInsert().
    $skus = array_values(array_filter(array_column($rows, 'sku')));

    $existingMap = []; // sku => id (oldest row wins)
    if (!empty($skus)) {
        DB::table('products')
            ->select('id', 'sku')
            ->where('shop_url', 'www.mobilesentrix.co.uk')
            ->whereIn('sku', $skus)
            ->orderBy('id')
            ->each(function ($product) use (&$existingMap) {
                // orderBy id + only set once => keep the oldest row
                if (!isset($existingMap[$product->sku])) {
                    $existingMap[$product->sku] = $product->id;
                }
            });
    }

    // 3. Split: rows with an existing DB id go to update; the rest go to insert.
    $toInsert = [];
    $toUpdate = []; // [ id => row ]

    foreach ($rows as $row) {
        $sku = $row['sku'] ?? null;

        if ($sku !== null && isset($existingMap[$sku])) {
            unset($row['created_at']); // never overwrite created_at on an existing row
            $row['id'] = $existingMap[$sku];
            $toUpdate[] = $row;
        } else {
            $toInsert[] = $row;
        }
    }

    // 4. ONE INSERT for all new rows.
    if (!empty($toInsert)) {
        DB::table('products')->insert($toInsert);
    }

    // 5. ONE bulk UPDATE for all existing rows using CASE WHEN (avoids N update queries).
    if (!empty($toUpdate)) {
        $this->bulkUpdateProducts($toUpdate);
    }
  }

  protected function bulkUpdateProducts(array $rows): void
  {
    $columns = [
        'title', 'description', 'link', 'main_image', 'additional_images',
        'price', 'sale_price', 'reference', 'colour', 'size',
        'stock', 'shop_url', 'variant_id', 'raw', 'updated_at',
    ];

    $ids = array_column($rows, 'id');

    // Build one UPDATE products SET col = CASE id WHEN x THEN y ... END WHERE id IN (...)
    // Chunked at 200 rows to keep binding counts reasonable.
    foreach (array_chunk($rows, 200) as $chunk) {
        $chunkIds = array_column($chunk, 'id');
        $setClauses = [];
        $bindings = [];

        foreach ($columns as $col) {
            $when = '';
            foreach ($chunk as $row) {
                $when .= ' WHEN ? THEN ?';
                $bindings[] = $row['id'];
                $bindings[] = $row[$col] ?? null;
            }
            $setClauses[] = "`{$col}` = CASE `id`{$when} ELSE `{$col}` END";
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

        return 'https://static.mobilesentrix.co.uk/' . ltrim($url, '/');
    }

    public function exportCsv($fileName): string
    {
        $filePath = storage_path('app/public/' . $fileName);
        // $products = Product::all();
        $query = Product::query()->where('shop_url', 'www.mobilesentrix.co.uk');

        $products = $query->get();


        if ($products->isEmpty()) {
            return "No products found to export.";
        }
        // dd($filePath);
        $handle = fopen($filePath, 'w');

        // CSV header
        fputcsv($handle, [
            'Product ID','Title', 'Part Number', 'Link', 'Main Image', 'Additional Images',
            'Price', 'Availability'
        ]);

        foreach ($products as $product) {

            
            if (empty($product->stock) || $product->stock <= 0) {
                continue;
            }
            

            // $googleCategory = !empty($product->categories)
            // ? implode(' > ', $product->categories)
            // : '';
            $descriptionArray = json_decode($product->description, true);

            $description = is_array($descriptionArray)
                ? implode(' ', $descriptionArray)
                : $product->description;

            fputcsv($handle, [
                $product->sku ?? '',
                $product->title ?? '',
                $product->reference ?? '',
                // $description ?? '',
                $product->link ?? '',
                $product->main_image ?? '',
                implode(', ', $product->additional_images ?? []),
                $product->price ?? '',
                'in stock',
            ]);
        }

        fclose($handle);

        return "CSV exported successfully to $filePath";
    }
}
