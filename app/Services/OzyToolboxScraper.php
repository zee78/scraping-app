<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Catalogue scraper for ozytoolbox.com.au.
 *
 * Product-specific specification pairs are saved both as Attribute rows and in
 * raw.specifications. This avoids losing fields which do not have a matching
 * column in the common products table (for example Material or Welding).
 */
class OzyToolboxScraper
{
    private const SOURCE = 'www.ozytoolbox.com.au';

    private Client $client;

    private string $baseUrl;

    private int $delayMs;

    private bool $browserMode;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ozy_toolbox.base_url'), '/');
        $this->delayMs = max(0, (int) config('services.ozy_toolbox.request_delay_ms', 500));
        $this->browserMode = (bool) config('services.ozy_toolbox.browser_mode', true);
        $this->client = new Client([
            'timeout' => 60,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-AU,en;q=0.9',
            ],
        ]);
    }

    /** @return array{path:string, discovered:int, stored:int, exported:int} */
    public function scrape(int $limit = 0, ?string $singleUrl = null): array
    {
        $urls = $singleUrl ? [$this->absoluteUrl($this->inputUrl($singleUrl))] : $this->discoverProductUrls($limit);
        if ($urls === []) {
            throw new \RuntimeException('No Ozy product URLs were found. If Cloudflare is showing a challenge, set OZY_TOOLBOX_BROWSER_MODE=true and configure OZY_TOOLBOX_CHROME_PATH.');
        }

        $stored = 0;
        $failures = [];
        foreach ($urls as $url) {
            try {
                $data = $this->parseProduct($url);
                if ($data !== null) {
                    $this->storeProduct($data);
                    $stored++;
                }
            } catch (\Throwable $e) {
                $failures[] = $e;
                logger()->warning('Ozy product skipped.', ['url' => $url, 'exception' => $e::class, 'message' => $e->getMessage()]);
            }
        }

        if ($stored === 0 && $failures !== []) {
            throw new \RuntimeException(
                $singleUrl
                    ? 'The supplied Ozy product could not be retrieved. Cloudflare is blocking the automated browser session; use an approved authenticated session or ask Ozy to allow this scraper.'
                    : 'No Ozy pages could be retrieved. Cloudflare is blocking the automated browser session; use an approved authenticated session or ask Ozy to allow this scraper.'
            );
        }

        $path = storage_path('app/public/ozy-toolbox-products.csv');
        $exported = $this->exportToCsv($path);

        return ['path' => $path, 'discovered' => count($urls), 'stored' => $stored, 'exported' => $exported];
    }

    /** @return list<string> */
    private function discoverProductUrls(int $limit): array
    {
        $urls = [];
        // Neto and similar catalogue platforms commonly expose one of these.
        // We follow sitemap indexes recursively, then validate each URL as a
        // product page while parsing, which also handles their unprefixed URLs.
        foreach (['/sitemap.xml', '/sitemap_index.xml', '/sitemap-products.xml'] as $path) {
            try {
                $this->collectSitemapUrls($this->absoluteUrl($path), $urls, []);
            } catch (\Throwable $e) {
                logger()->info('Ozy sitemap was unavailable.', ['url' => $path, 'exception' => $e::class]);
            }
        }

        if ($urls === []) {
            $home = new Crawler($this->fetch($this->baseUrl), $this->baseUrl);
            $home->filter('a[href]')->each(function (Crawler $link) use (&$urls): void {
                $url = $this->absoluteUrl((string) $link->attr('href'));
                if ($this->isInternalHtmlUrl($url)) {
                    $urls[$url] = $url;
                }
            });
        }

        $urls = array_values($urls);

        return $limit > 0 ? array_slice($urls, 0, $limit) : $urls;
    }

    /** @param array<string,string> $urls @param array<string,bool> $seen */
    private function collectSitemapUrls(string $sitemapUrl, array &$urls, array $seen): void
    {
        if (isset($seen[$sitemapUrl])) {
            return;
        }
        $seen[$sitemapUrl] = true;
        $xml = $this->fetch($sitemapUrl);
        if (stripos($xml, '<urlset') === false && stripos($xml, '<sitemapindex') === false) {
            return;
        }

        $crawler = new Crawler($xml, $sitemapUrl);
        $crawler->filter('loc')->each(function (Crawler $loc) use (&$urls, $seen): void {
            $url = trim($loc->text(''));
            if ($url === '') {
                return;
            }
            if (preg_match('/\.xml(?:\?|$)/i', $url)) {
                $this->collectSitemapUrls($url, $urls, $seen);
            } elseif ($this->isInternalHtmlUrl($url)) {
                $urls[$url] = $url;
            }
        });
    }

    /** @return array<string,mixed>|null */
    private function parseProduct(string $url): ?array
    {
        $crawler = new Crawler($this->fetch($url), $url);
        $json = $this->jsonLdProduct($crawler);
        $title = $this->firstText($crawler, ['h1', '[itemprop="name"]', '.product-title', '.product-name']);
        $title = $title ?: trim((string) ($json['name'] ?? ''));
        if ($title === '') {
            return null;
        }

        $description = $this->firstHtml($crawler, ['[itemprop="description"]', '.product-description', '.description', '#description']);
        $description = $description ?: (string) ($json['description'] ?? '');
        $specifications = $this->specifications($crawler);
        $videos = $this->videos($crawler);
        $images = $this->images($crawler, $json['image'] ?? null);
        $offer = is_array($json['offers'] ?? null) && ! array_is_list($json['offers']) ? $json['offers'] : [];
        $sku = $this->firstText($crawler, ['[itemprop="sku"]', '.sku', '.product-sku', '.product-code']);
        $sku = $sku ?: trim((string) ($json['sku'] ?? ''));
        $availability = $this->availability($crawler->text('').' '.($offer['availability'] ?? ''));
        $categories = $this->categories($crawler);

        return [
            'source_id' => 'ozy:'.sha1($url),
            'shop_url' => self::SOURCE,
            'sku' => $sku ?: null,
            'title' => $title,
            'description' => $description,
            'link' => $url,
            'main_image' => $images[0] ?? null,
            'additional_images' => array_slice($images, 1),
            'price' => $this->price($offer['price'] ?? $this->firstText($crawler, ['[itemprop="price"]', '.price', '.product-price'])),
            'sale_price' => $this->price($offer['price'] ?? $this->firstText($crawler, ['[itemprop="price"]', '.price', '.product-price'])),
            'reference' => $this->firstText($crawler, ['[itemprop="mpn"]', '.mpn', '.reference']),
            'colour' => $this->attributeValue($specifications, 'colour|color'),
            'size' => $this->attributeValue($specifications, 'size|dimension'),
            'stock' => $availability === 'out of stock' ? 0 : 1,
            'categories' => $categories,
            'video_url' => $videos[0] ?? null,
            'specifications' => $specifications,
            'raw' => ['schema' => $json, 'specifications' => $specifications, 'video_urls' => $videos, 'availability' => $availability],
        ];
    }

    /** @param array<string,mixed> $data */
    private function storeProduct(array $data): void
    {
        $specifications = $data['specifications'];
        unset($data['specifications']);
        $product = Product::query()->updateOrCreate(
            ['shop_url' => self::SOURCE, 'source_id' => $data['source_id']],
            $data,
        );
        Attribute::query()->where('product_id', $product->id)->delete();
        foreach ($specifications as $key => $value) {
            Attribute::create(['product_id' => $product->id, 'key' => $key, 'value' => $value]);
        }
    }

    private function exportToCsv(string $path): int
    {
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Unable to write {$path}");
        }
        fputcsv($handle, ['Product ID', 'SKU', 'Title', 'Description', 'Link', 'Main Image', 'Additional Images', 'Price', 'Sale Price', 'Reference', 'Colour', 'Size', 'Stock', 'Categories', 'Video URL', 'Specifications']);
        $exported = 0;
        Product::query()->where('shop_url', self::SOURCE)->orderBy('id')->each(function (Product $product) use ($handle, &$exported): void {
            $specifications = Attribute::query()->where('product_id', $product->id)->pluck('value', 'key')->all();
            fputcsv($handle, [
                $product->source_id, $product->sku, $product->title, $product->description, $product->link,
                $product->main_image, implode(' | ', $product->additional_images ?? []), $product->price,
                $product->sale_price, $product->reference, $product->colour, $product->size, $product->stock,
                implode(' > ', $product->categories ?? []), $product->video_url, json_encode($specifications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $exported++;
        });
        fclose($handle);

        return $exported;
    }

    /** @return array<string,string> */
    private function specifications(Crawler $crawler): array
    {
        $fields = [];
        $titles = $crawler->filter('.description-sub-title')->each(fn (Crawler $node) => $this->clean($node->text('')));
        $values = $crawler->filter('.description-sub-description')->each(fn (Crawler $node) => $this->clean($node->text('')));
        foreach ($titles as $index => $title) {
            $value = $values[$index] ?? '';
            if ($title !== '' && $value !== '') {
                $fields[$title] = $value;
            }
        }
        $crawler->filter('table tr')->each(function (Crawler $row) use (&$fields): void {
            $cells = $row->filter('th, td')->each(fn (Crawler $cell) => $this->clean($cell->text('')));
            if (count($cells) >= 2 && $cells[0] !== '' && $cells[1] !== '') {
                $fields[rtrim($cells[0], ':')] = $cells[1];
            }
        });
        $crawler->filter('dl')->each(function (Crawler $list) use (&$fields): void {
            $keys = $list->filter('dt')->each(fn (Crawler $node) => $this->clean($node->text('')));
            $values = $list->filter('dd')->each(fn (Crawler $node) => $this->clean($node->text('')));
            foreach ($keys as $i => $key) {
                if ($key !== '' && ($values[$i] ?? '') !== '') {
                    $fields[rtrim($key, ':')] = $values[$i];
                }
            }
        });

        return $fields;
    }

    /** @return list<string> */
    private function videos(Crawler $crawler): array
    {
        $urls = $crawler->filter('video[src], video source[src], iframe[src*="youtube"], iframe[src*="vimeo"]')->each(fn (Crawler $node) => $node->attr('src'));

        return array_values(array_unique(array_filter(array_map(fn ($url) => $this->absoluteUrl((string) $url), $urls))));
    }

    /** @return list<string> */
    private function images(Crawler $crawler, mixed $schemaImage): array
    {
        $images = is_array($schemaImage) ? $schemaImage : [$schemaImage];
        foreach ($crawler->filter('[itemprop="image"], .product-image img, .product-gallery img, .product-thumbnails img')->each(fn (Crawler $node) => $node->attr('data-src') ?: $node->attr('src')) as $image) {
            $images[] = $image;
        }

        return array_values(array_unique(array_filter(array_map(fn ($image) => $this->absoluteUrl((string) $image), $images))));
    }

    /** @return array<string,mixed> */
    private function jsonLdProduct(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]')->each(fn (Crawler $node) => $node->text('')) as $script) {
            $decoded = json_decode($script, true);
            foreach (($decoded['@graph'] ?? [$decoded]) as $item) {
                if (is_array($item) && (in_array('Product', (array) ($item['@type'] ?? []), true))) {
                    return $item;
                }
            }
        }

        return [];
    }

    /** @return list<string> */
    private function categories(Crawler $crawler): array
    {
        foreach (['.breadcrumb a', '.breadcrumbs a', 'nav[aria-label="breadcrumb"] a'] as $selector) {
            if ($crawler->filter($selector)->count()) {
                return array_values(array_filter($crawler->filter($selector)->each(fn (Crawler $node) => $this->clean($node->text('')))));
            }
        }

        return [];
    }

    private function fetch(string $url): string
    {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
        if ($this->browserMode) {
            $browser = Browsershot::url($url)->setNodeModulePath(base_path('node_modules'))->windowSize(1440, 1200)->newHeadless()->timeout(120)->setDelay(750);
            // The site keeps third-party connections open. Waiting for the full
            // load event causes Puppeteer to give up before the DOM is usable.
            $browser->setOption('waitUntil', 'domcontentloaded');
            $browser->setOption('protocolTimeout', 120000);
            $chromePath = $this->chromePath();
            if ($chromePath !== '') {
                $browser->setChromePath($chromePath);
            }
            $html = $browser->bodyHtml();
        } else {
            $response = $this->client->get($url);
            $html = (string) $response->getBody();
        }
        if (str_contains(strtolower($html), 'just a moment') || str_contains(strtolower($html), 'cf-chl-')) {
            throw new \RuntimeException("Cloudflare challenge received for {$url}");
        }

        return $html;
    }

    private function firstText(Crawler $crawler, array $selectors): string
    {
        foreach ($selectors as $selector) {
            if ($crawler->filter($selector)->count()) {
                return $this->clean($crawler->filter($selector)->first()->text(''));
            }
        }

        return '';
    }

    private function firstHtml(Crawler $crawler, array $selectors): string
    {
        foreach ($selectors as $selector) {
            if ($crawler->filter($selector)->count()) {
                return trim((string) $crawler->filter($selector)->first()->html());
            }
        }

        return '';
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    private function price(mixed $value): string
    {
        return preg_replace('/[^0-9.]/', '', (string) $value) ?: '';
    }

    private function attributeValue(array $fields, string $pattern): string
    {
        foreach ($fields as $key => $value) {
            if (preg_match('/'.$pattern.'/i', $key)) {
                return $value;
            }
        }

        return '';
    }

    private function availability(string $text): string
    {
        $text = strtolower($text);

        return str_contains($text, 'out of stock') ? 'out of stock' : (str_contains($text, 'pre-order') ? 'pre-order' : 'in stock');
    }

    private function isInternalHtmlUrl(string $url): bool
    {
        return str_starts_with($url, $this->baseUrl) && ! preg_match('/\.(?:jpg|jpeg|png|gif|webp|pdf|xml|mov|mp4)(?:\?|$)/i', $url) && ! str_contains($url, '#');
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim(html_entity_decode($url));
        if ($url === '') {
            return $this->baseUrl;
        } if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        } if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return $this->baseUrl.'/'.ltrim($url, '/');
    }

    /** Accept a URL pasted as Markdown, e.g. [title](https://example.test/product). */
    private function inputUrl(string $url): string
    {
        if (preg_match('/^\s*\[[^\]]*\]\((https?:\/\/[^)]+)\)\s*$/i', $url, $matches)) {
            return $matches[1];
        }

        return $url;
    }

    private function chromePath(): string
    {
        $configured = trim((string) config('services.ozy_toolbox.chrome_path'));
        if ($configured !== '') {
            return $configured;
        }

        foreach ([
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        ] as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
