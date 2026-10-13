<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Authenticated PartsBox catalogue exporter.
 *
 * It deliberately writes directly to a source-specific CSV rather than sharing the
 * products table: several existing scrapers identify products by SKU globally and a
 * supplier SKU can overlap with another supplier's SKU.
 */
class PartsBoxScraper
{
    private const SOURCE = 'www.partsbox.co.uk';

    private Client $client;
    private CookieJar $cookies;
    private string $baseUrl;
    private int $delayMs;
    private bool $browserMode;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.partsbox.base_url'), '/');
        $this->delayMs = max(0, (int) config('services.partsbox.request_delay_ms', 350));
        $this->browserMode = (bool) config('services.partsbox.browser_mode', true);
        $this->cookies = new CookieJar();
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => $this->cookies,
            'timeout' => 60,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.9',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'same-origin',
            ],
        ]);
    }

    /** @return array{path:string, exported:int, out_of_stock:int, accessories:int} */
    public function scrape(int $limit = 0, ?string $cataloguePath = null): array
    {
        if (!$this->browserMode) {
            throw new \RuntimeException('PartsBox requires PARTSBOX_BROWSER_MODE=true because its portal rejects direct HTTP clients.');
        }
        $this->loginWithBrowser();

        $inventoryUrl = $this->absoluteUrl($cataloguePath ?: (string) config('services.partsbox.catalogue_path'));
        $brands = $this->brandsFromDashboard($this->browserHtml($inventoryUrl));
        if ($brands === []) {
            throw new \RuntimeException('No brands were found in the authenticated PartsBox inventory dashboard.');
        }
        logger()->info('PartsBox scrape started.', ['brand_count' => count($brands), 'limit' => $limit]);

        $outOfStock = $accessories = $processed = $stored = 0;
        foreach ($brands as $brand) {
            if (!is_array($brand)) {
                logger()->warning('PartsBox skipped a non-object brand record.', ['record_type' => get_debug_type($brand)]);
                continue;
            }
            $brandId = $brand['id'] ?? null;
            $brandName = $brand['name'] ?? null;
            if (!is_string($brandId) || !is_string($brandName)) {
                logger()->warning('PartsBox skipped an invalid brand record.', ['keys' => array_keys($brand)]);
                continue;
            }
            if ($this->isAccessory($brandName)) {
                $accessories++;
                logger()->info('PartsBox skipped accessories brand.', ['brand' => $brandName]);
                continue;
            }
            $modelsPath = '/api/portal/browse/models?brandId=' . rawurlencode($brandId);
            $models = $this->browserJson($modelsPath);
            logger()->info('PartsBox models loaded.', ['brand' => $brandName, 'count' => count($models)]);
            foreach ($models as $model) {
                if (!is_array($model) || !isset($model['id'])) {
                    logger()->warning('PartsBox skipped an invalid model record.', [
                        'brand' => $brandName,
                        'response_path' => $modelsPath,
                        'record_type' => get_debug_type($model),
                        'keys' => is_array($model) ? array_keys($model) : [],
                    ]);
                    continue;
                }
                $modelId = (string) $model['id'];
                $modelName = (string) ($model['name'] ?? '');
                $page = 1;
                while (true) {
                    $partsPath = '/api/portal/inventory?modelId=' . rawurlencode($modelId) . '&page=' . $page;
                    $parts = $this->browserJson($partsPath);
                    logger()->info('PartsBox inventory page loaded.', ['brand' => $brandName, 'model' => $modelName, 'page' => $page, 'count' => count($parts)]);
                    if ($parts === []) break;
                    foreach ($parts as $part) {
                        if ($limit > 0 && $processed >= $limit) break 4;
                        if (!is_array($part)) {
                            logger()->warning('PartsBox skipped a non-object inventory record.', ['response_path' => $partsPath, 'record_type' => get_debug_type($part)]);
                            continue;
                        }
                        $processed++;
                        $availability = str_replace('_', ' ', strtolower((string) ($part['stockStatus'] ?? 'unknown')));
                        if ($this->storePart($part, $brandName, $modelId, $modelName, $inventoryUrl, $availability)) {
                            $stored++;
                        }
                        if ($availability === 'out of stock') {
                            $outOfStock++;
                            continue;
                        }
                    }
                    $page++;
                }
            }
        }
        $path = storage_path('app/public/partsbox_products.csv');
        $exported = $this->exportToCsv($path);
        logger()->info('PartsBox scrape completed.', compact('exported', 'stored', 'outOfStock', 'accessories', 'processed', 'path'));

        return compact('path', 'exported', 'outOfStock', 'accessories') + ['out_of_stock' => $outOfStock];
    }

    private function storePart(array $part, string $brandName, string $modelId, string $modelName, string $inventoryUrl, string $availability): bool
    {
        $partId = (string) ($part['id'] ?? '');
        if ($partId === '') {
            logger()->warning('PartsBox skipped a product without a stable ID.', ['sku' => $part['sku'] ?? null]);
            return false;
        }

        $categories = array_values(array_filter([
            $part['brand'] ?? $brandName,
            $part['model'] ?? $modelName,
            $part['category'] ?? '',
        ]));

        $identity = ['shop_url' => self::SOURCE, 'source_id' => 'partsbox:' . $partId];
        $values = [
            'sku' => $part['sku'] ?? null,
            'title' => $part['partName'] ?? null,
            'description' => $part['description'] ?? null,
            'link' => $inventoryUrl . '?modelId=' . rawurlencode($modelId),
            'main_image' => $part['imageUrl'] ?? null,
            'additional_images' => [],
            'price' => $part['price'] ?? null,
            'sale_price' => $part['price'] ?? null,
            'reference' => $part['sku'] ?? null,
            'colour' => $part['color'] ?? null,
            'stock' => (int) ($part['availableQty'] ?? 0),
            'categories' => $categories,
            'raw' => array_merge($part, ['availability' => $availability]),
        ];

        // PartsBox's API ID is stable. Check this supplier's records explicitly
        // before inserting, so no database uniqueness constraint is required.
        $existing = Product::query()->where($identity)->first();
        if ($existing) {
            $existing->update($values);
        } else {
            Product::create($identity + $values);
        }

        return true;
    }

    private function exportToCsv(string $path): int
    {
        $handle = fopen($path, 'w');
        if ($handle === false) throw new \RuntimeException("Unable to create CSV at {$path}");
        fputcsv($handle, ['Product ID', 'Title', 'Description', 'Link', 'Main Image', 'Additional Images', 'Price', 'Sale Price', 'Reference', 'Colour', 'Size', 'Stock', 'Categories', 'Availability', 'Master Category']);

        $exported = 0;
        Product::query()
            ->where('shop_url', self::SOURCE)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->each(function (Product $product) use ($handle, &$exported): void {
                $categories = $product->categories ?? [];
                $raw = $product->raw ?? [];
                $availability = str_replace('_', ' ', (string) ($raw['stockStatus'] ?? $raw['availability'] ?? 'in stock'));
                fputcsv($handle, [
                    $product->sku ?? $product->source_id,
                    $product->title ?? '',
                    $product->description ?? '',
                    $product->link ?? '',
                    $product->main_image ?? '',
                    implode(', ', $product->additional_images ?? []),
                    $product->price ?? '',
                    $product->sale_price ?? '',
                    $product->reference ?? '',
                    $product->colour ?? '',
                    $product->size ?? '',
                    $product->stock ?? '',
                    implode(', ', $categories),
                    $availability,
                    implode(' > ', $categories),
                ]);
                $exported++;
            });
        fclose($handle);

        return $exported;
    }

    private function login(): void
    {
        $email = (string) config('services.partsbox.email');
        $password = (string) config('services.partsbox.password');
        if ($email === '' || $password === '') {
            throw new \RuntimeException('Set PARTSBOX_EMAIL and PARTSBOX_PASSWORD in .env before running this scraper.');
        }

        $loginUrl = $this->absoluteUrl((string) config('services.partsbox.login_path'));
        $html = $this->get($loginUrl, false);
        $crawler = new Crawler($html, $loginUrl);
        $form = $crawler->filter('form')->reduce(function (Crawler $node): bool {
            return $node->filter('input[type="password"]')->count() > 0;
        })->first();

        if ($form->count() === 0) {
            throw new \RuntimeException('PartsBox login form was not found (the site may be rate-limiting this server).');
        }

        $fields = [];
        $form->filter('input[name]')->each(function (Crawler $input) use (&$fields, $email, $password): void {
            $name = $input->attr('name');
            $type = strtolower((string) $input->attr('type'));
            $fields[$name] = match (true) {
                $type === 'password' => $password,
                str_contains(strtolower($name), 'email'),
                str_contains(strtolower($name), 'user'),
                str_contains(strtolower($name), 'login') => $email,
                default => $input->attr('value') ?? '',
            };
        });

        $action = $form->attr('action') ?: $loginUrl;
        $response = $this->client->post($this->absoluteUrl($action), [
            'form_params' => $fields,
            'headers' => ['Referer' => $loginUrl, 'Origin' => $this->baseUrl],
            'allow_redirects' => true,
        ]);
        $body = (string) $response->getBody();
        if ($response->getStatusCode() >= 400 || $this->looksLikeLoginPage($body)) {
            throw new \RuntimeException('PartsBox login failed. Check the credentials, or wait for the portal rate limit to clear.');
        }
    }

    /** @return list<array{id:string,name:string}> */
    private function brandsFromDashboard(string $html): array
    {
        // Next serializes the server-side props into script text. Decode that text
        // before extracting the brands array rather than relying on CSS button order.
        $source = str_replace(['\\"', '&quot;'], ['"', '"'], $html);
        if (!preg_match('/"brands":(\[.*?\]),"categories":/s', $source, $matches)) {
            return [];
        }
        $brands = json_decode($matches[1], true);
        return is_array($brands) ? array_values(array_filter($brands, fn ($brand) => isset($brand['id'], $brand['name']))) : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function browserJson(string $path): array
    {
        $url = $this->absoluteUrl($path);
        $json = $this->browser($this->absoluteUrl('/portal/inventory'))->evaluate(
            '(async () => {'
            . 'const response = await fetch(' . json_encode($url, JSON_THROW_ON_ERROR) . ', {credentials: "include"});'
            . 'if (!response.ok) throw new Error("PartsBox API returned HTTP " + response.status);'
            . 'return await response.text();'
            . '})()'
        );
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("PartsBox returned invalid JSON from {$path}");
        }
        // The API can return either the bare list seen in DevTools or a
        // {data: [...], error: null} envelope when called through the portal.
        if (array_key_exists('data', $data)) {
            if (($data['error'] ?? null) !== null) {
                throw new \RuntimeException('PartsBox API error from ' . $path . ': ' . (string) $data['error']);
            }
            if (!is_array($data['data'])) {
                throw new \RuntimeException("PartsBox API returned a non-list data payload from {$path}");
            }
            $data = $data['data'];
        }
        logger()->debug('PartsBox API response received.', [
            'path' => $path,
            'record_count' => count($data),
            'top_level_keys' => array_is_list($data) ? [] : array_keys($data),
            'first_record_keys' => isset($data[0]) && is_array($data[0]) ? array_keys($data[0]) : [],
        ]);
        return $data;
    }

    /**
     * Uses an isolated, persistent Chrome profile. This is necessary because
     * PartsBox rate-limits non-browser HTTP clients even when the same IP can
     * sign in normally through Chrome.
     */
    private function loginWithBrowser(): void
    {
        $email = (string) config('services.partsbox.email');
        $password = (string) config('services.partsbox.password');
        if ($email === '' || $password === '') {
            throw new \RuntimeException('Set PARTSBOX_EMAIL and PARTSBOX_PASSWORD in .env before running this scraper.');
        }

        $loginUrl = $this->absoluteUrl((string) config('services.partsbox.login_path'));
        $initialHtml = $this->browser($loginUrl)->setDelay(500)->bodyHtml();
        // A persistent Chrome profile may already be signed in. PartsBox redirects
        // /portal/login to /portal/inventory in that case, which is a success.
        if (str_contains($initialHtml, 'Browse Stock') && !str_contains($initialHtml, 'id="password"')) {
            return;
        }

        // The portal is a single-page application that keeps background network
        // requests open. Wait for its actual login field instead of network-idle.
        $crawler = new Crawler($initialHtml, $loginUrl);
        $form = $crawler->filter('form')->reduce(fn (Crawler $node): bool => $node->filter('input[type="password"]')->count() > 0)->first();
        if ($form->count() === 0) {
            throw new \RuntimeException('PartsBox login form was not found in Chrome. Check PARTSBOX_LOGIN_PATH.');
        }

        // This is a React-controlled form. It intentionally has no HTML form
        // action or input names, but its IDs are stable in the supplied markup.
        $emailSelector = $crawler->filter('#email')->count() ? '#email' : '';
        $passwordSelector = $crawler->filter('#password')->count() ? '#password' : '';
        $form->filter('input[name]')->each(function (Crawler $input) use (&$emailSelector, &$passwordSelector): void {
            $name = (string) $input->attr('name');
            $type = strtolower((string) $input->attr('type'));
            if ($passwordSelector === '' && $type === 'password') {
                $passwordSelector = 'input[name="' . addcslashes($name, '\\\"') . '"]';
            } elseif ($emailSelector === '' && ($type === 'email' || str_contains(strtolower($name), 'email') || str_contains(strtolower($name), 'user'))) {
                $emailSelector = 'input[name="' . addcslashes($name, '\\\"') . '"]';
            }
        });
        if ($emailSelector === '' || $passwordSelector === '') {
            throw new \RuntimeException('Could not identify the PartsBox email/password fields in Chrome.');
        }

        // Browsershot keeps the isolated Chrome profile on disk, so the next
        // page launch reuses the authenticated session cookie.
        $afterLoginHtml = '';
        $navigationException = null;
        try {
            $afterLoginHtml = $this->browser($loginUrl)
                ->type($emailSelector, $email, 20)
                ->type($passwordSelector, $password, 20)
                ->click('form button[type="submit"]')
                ->setDelay(2500)
                ->bodyHtml();
        } catch (\Throwable $exception) {
            // Browsershot asks Puppeteer for the old page's HTML immediately
            // after submit. PartsBox replaces that page during successful login,
            // causing Puppeteer's harmless "execution context was destroyed"
            // exception. Verify the persistent session below before failing.
            $navigationException = $exception;
            logger()->info('PartsBox login triggered a navigation; verifying the browser session.', [
                'exception' => $exception::class,
            ]);
        }

        usleep(750_000);
        try {
            $dashboardHtml = $this->browserHtml($this->absoluteUrl('/portal/inventory'));
            if (str_contains($dashboardHtml, 'Browse Stock') && !str_contains($dashboardHtml, 'id="password"')) {
                return;
            }
        } catch (\Throwable $exception) {
            if ($navigationException === null) {
                $navigationException = $exception;
            }
        }

        if ($navigationException !== null) {
            throw new \RuntimeException('PartsBox browser login did not create an authenticated session.', 0, $navigationException);
        }
        if ($this->looksLikeLoginPage($afterLoginHtml)) {
            throw new \RuntimeException('PartsBox browser login did not complete. Confirm the portal has no CAPTCHA or two-factor prompt.');
        }
    }

    /** @return list<string> */
    private function discoverProductUrls(string $catalogueUrl, int $limit): array
    {
        $queue = [$catalogueUrl];
        $seenPages = [];
        $productUrls = [];

        while ($queue !== [] && ($limit <= 0 || count($productUrls) < $limit)) {
            $pageUrl = array_shift($queue);
            if (isset($seenPages[$pageUrl])) {
                continue;
            }
            $seenPages[$pageUrl] = true;
            $crawler = new Crawler($this->get($pageUrl), $pageUrl);

            $crawler->filter('a[href]')->each(function (Crawler $link) use (&$queue, &$productUrls, $limit): void {
                $href = $link->attr('href');
                $url = $this->absoluteUrl($href);
                $label = strtolower(trim($link->text('') . ' ' . $href));
                if ($this->isAccessory($label)) {
                    return;
                }
                if ($this->isProductUrl($url, $label)) {
                    $productUrls[$url] = $url;
                    return;
                }
                if ($this->isCatalogueUrl($url, $label) && count($queue) < 500) {
                    $queue[] = $url;
                }
            });
        }

        $urls = array_values($productUrls);
        return $limit > 0 ? array_slice($urls, 0, $limit) : $urls;
    }

    /** @return array<string, mixed>|null */
    private function parseProduct(string $url): ?array
    {
        $crawler = new Crawler($this->get($url), $url);
        $jsonProduct = $this->jsonLdProduct($crawler);
        $text = strtolower(preg_replace('/\s+/', ' ', $crawler->text('')) ?: '');
        $categories = $this->categories($crawler);

        $title = $jsonProduct['name'] ?? $this->firstText($crawler, ['h1', '[itemprop="name"]', '.product-title']);
        if ($title === '') {
            return null;
        }
        $availability = $this->availability($text, (string) ($jsonProduct['offers']['availability'] ?? ''));
        $sku = (string) ($jsonProduct['sku'] ?? $this->firstText($crawler, ['[itemprop="sku"]', '.sku', '.product-sku']));
        $price = $this->price($jsonProduct['offers']['price'] ?? $this->firstText($crawler, ['[itemprop="price"]', '.price']));
        $images = $this->images($crawler, $jsonProduct['image'] ?? null);
        $description = $jsonProduct['description'] ?? $this->firstHtml($crawler, ['[itemprop="description"]', '.description', '.product-description']);

        return [
            'url' => $url,
            'title' => trim(strip_tags((string) $title)),
            'sku' => trim($sku),
            'reference' => trim($this->firstText($crawler, ['.mpn', '[itemprop="mpn"]', '.reference'])),
            'description' => trim((string) $description),
            'price' => $price,
            'sale_price' => $price,
            'colour' => trim($this->labelValue($crawler, 'colour|color')),
            'size' => trim($this->labelValue($crawler, 'size')),
            'stock' => $availability === 'low stock' ? 1 : ($availability === 'in stock' ? 1 : 0),
            'availability' => $availability,
            'categories' => $categories,
            'accessory' => $this->isAccessory($text . ' ' . implode(' ', $categories) . ' ' . $url),
            'main_image' => $images[0] ?? '',
            'additional_images' => array_slice($images, 1),
        ];
    }

    private function get(string $url, bool $throttle = true): string
    {
        if ($this->browserMode) {
            if ($throttle && $this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
            return $this->browserHtml($url);
        }

        if ($throttle && $this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
        $attempts = max(1, (int) config('services.partsbox.rate_limit_retries', 3));
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = $this->client->get($url);
            if ($response->getStatusCode() !== 429) {
                break;
            }

            if ($attempt === $attempts) {
                throw new \RuntimeException(
                    'PartsBox returned HTTP 429 after ' . $attempts . ' attempts. '
                    . 'The portal has rate-limited this IP; wait for its Retry-After window, then run the command again.'
                );
            }

            $retryAfter = $this->retryAfterSeconds($response->getHeaderLine('Retry-After'), $attempt);
            logger()->warning('PartsBox rate limited the request; waiting before retrying.', [
                'url' => $url,
                'attempt' => $attempt,
                'wait_seconds' => $retryAfter,
            ]);
            sleep($retryAfter);
        }
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("PartsBox returned HTTP {$response->getStatusCode()} for {$url}");
        }
        return (string) $response->getBody();
    }

    private function browserHtml(string $url): string
    {
        return $this->browser($url)
            ->setDelay(500)
            ->bodyHtml();
    }

    private function browser(string $url): Browsershot
    {
        $profile = storage_path('app/partsbox-chrome-profile');
        File::ensureDirectoryExists($profile);
        $browser = Browsershot::url($url)
            ->setNodeModulePath(base_path('node_modules'))
            ->setUserDataDir($profile)
            ->windowSize(1440, 1000)
            ->timeout(120);

        $chromePath = (string) config('services.partsbox.chrome_path');
        if ($chromePath !== '') {
            $browser->setChromePath($chromePath);
        }

        return $browser;
    }

    private function retryAfterSeconds(string $header, int $attempt): int
    {
        if (ctype_digit($header)) {
            return min(300, max(5, (int) $header));
        }

        if ($header !== '' && ($timestamp = strtotime($header)) !== false) {
            return min(300, max(5, $timestamp - time()));
        }

        return min(120, 15 * (2 ** ($attempt - 1)));
    }

    private function absoluteUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') return $this->baseUrl;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    private function isProductUrl(string $url, string $label): bool
    {
        return (bool) preg_match('#/(product|products|item|items|part|parts)/[^/?#]+#i', parse_url($url, PHP_URL_PATH) ?: '')
            && !str_contains($label, 'category');
    }

    private function isCatalogueUrl(string $url, string $label): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return str_starts_with($url, $this->baseUrl)
            && (str_contains($label, 'catalog') || str_contains($label, 'category') || str_contains($label, 'next')
                || str_contains($path, '/portal/'));
    }

    private function isAccessory(string $value): bool
    {
        return str_contains(strtolower($value), 'accessor');
    }

    private function looksLikeLoginPage(string $html): bool
    {
        $crawler = new Crawler($html);
        return $crawler->filter('input[type="password"]')->count() > 0;
    }

    private function availability(string $pageText, string $schemaAvailability): string
    {
        $value = strtolower($pageText . ' ' . $schemaAvailability);
        if (str_contains($value, 'out of stock') || str_contains($value, 'outofstock')) return 'out of stock';
        if (str_contains($value, 'low stock') || str_contains($value, 'limited stock')) return 'low stock';
        if (str_contains($value, 'in stock') || str_contains($value, 'instock')) return 'in stock';
        return 'unknown';
    }

    /** @return array<string, mixed> */
    private function jsonLdProduct(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]')->each(fn (Crawler $node) => $node->text('')) as $json) {
            $data = json_decode($json, true);
            $items = isset($data['@graph']) ? $data['@graph'] : [$data];
            foreach ($items as $item) {
                if (is_array($item) && (($item['@type'] ?? null) === 'Product' || in_array('Product', (array) ($item['@type'] ?? []), true))) return $item;
            }
        }
        return [];
    }

    /** @return list<string> */
    private function categories(Crawler $crawler): array
    {
        $values = [];
        foreach (['nav[aria-label="breadcrumb"] a', '.breadcrumb a', '[itemprop="breadcrumb"]'] as $selector) {
            if ($crawler->filter($selector)->count()) {
                $values = $crawler->filter($selector)->each(fn (Crawler $node) => trim($node->text('')));
                break;
            }
        }
        return array_values(array_unique(array_filter($values)));
    }

    /** @return list<string> */
    private function images(Crawler $crawler, mixed $schemaImage): array
    {
        $images = is_array($schemaImage) ? $schemaImage : [$schemaImage];
        foreach ($crawler->filter('[itemprop="image"], .product-image img, .gallery img')->each(fn (Crawler $node) => $node->attr('src') ?: $node->attr('data-src')) as $image) $images[] = $image;
        return array_values(array_unique(array_filter(array_map(fn ($image) => $this->absoluteUrl((string) $image), $images))));
    }

    private function firstText(Crawler $crawler, array $selectors): string
    {
        foreach ($selectors as $selector) if ($crawler->filter($selector)->count()) return trim($crawler->filter($selector)->first()->text(''));
        return '';
    }

    private function firstHtml(Crawler $crawler, array $selectors): string
    {
        foreach ($selectors as $selector) if ($crawler->filter($selector)->count()) return trim((string) $crawler->filter($selector)->first()->html());
        return '';
    }

    private function labelValue(Crawler $crawler, string $label): string
    {
        $xpath = sprintf('//*[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "%s")]', strtolower(explode('|', $label)[0]));
        foreach ($crawler->filterXPath($xpath) as $node) {
            $value = trim($node->textContent ?? '');
            if (preg_match('/(?:' . $label . ')\s*:?\s*(.+)/i', $value, $matches)) return $matches[1];
        }
        return '';
    }

    private function price(mixed $value): string
    {
        return preg_replace('/[^0-9.]/', '', (string) $value) ?: '';
    }
}
