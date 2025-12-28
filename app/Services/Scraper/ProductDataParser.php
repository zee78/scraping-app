<?php

namespace App\Services\Scraper;

use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ProductDataParser
{
    public function parse(Crawler $crawler, string $url): ?array
    {
        if (str_starts_with($url, 'https://koinis.gr/en')) {
            return null;
        }

        // ✅ Only allow Greek URLs starting with /el/Αρχική
        if (!str_starts_with($url, 'https://koinis.gr/el')) {
            return null;
        }

        $data = [
            'link' => $url,
            'sku' => $this->getProductID($crawler),
            'title' => $this->getTitle($crawler),
            'description' => $this->getDescription($crawler),
            'image_link' => $this->getMainImage($crawler),
            'additional_images' => $this->getAdditionalImages($crawler),
            'price' => $this->getPrice($crawler),
            'sale_price' => $this->getSalePrice($crawler),
            'categories' => $this->getCategories($crawler),
            'reference_number' => $this->getReference($crawler),
            'colour' => $this->getColour($crawler),
            'size' => $this->getSize($crawler),
            'stock' => $this->getStock($crawler),
            'variants'=> $this->parseVariants($crawler, $url),
            'attributes' => $this->getFeatures($crawler),
        ];

        // minimal validation - require link + title
        if (empty($data['link']) || empty($data['title'])) {
            return null;
        }

        return $data;
    }

    protected function getProductID(Crawler $c)
    {
        $productId = $c->filter('meta[itemprop="sku"]')->count()
            ? $c->filter('meta[itemprop="sku"]')->attr('content')
            : null;
        
        return $productId;
    }
    protected function getTitle(Crawler $c)
    {
        $candidates = [
            'h1.h1',
            'h1[itemprop="name"]',
            '.product-title h1',
            '.page-title h1',
        ];

        foreach ($candidates as $sel) {
            if ($c->filter($sel)->count()) {
                return trim($c->filter($sel)->first()->text());
            }
        }
        return null;
    }
    protected function getDescription(Crawler $c)
    {
        $selectors = [
            '.product-description',
        ];

       
        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                $node = $c->filter($sel)->first();

                // Find <p> or <div> children
                $children = $node->filter('p, div');

                if ($children->count() > 0) {
                    $paragraphs = [];
                    $children->each(function (Crawler $child) use (&$paragraphs) {
                        $text = trim($child->text());
                        if ($text !== '') {
                            $paragraphs[] = $text;
                        }
                    });

                    return implode("\n\n", $paragraphs);
                }

                // No children, return container's own text
                $text = trim($node->text());
                return $text !== '' ? $text : null;
            }
        }
        return null;
    }

    protected function getMainImage(Crawler $c)
    {
        $selectors = [
            '#main-image img',
            '.product-cover img',
            '.zoomed img',
            '.product-image img',
            '#bigpic',
            '.primary-image img',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                return $this->normalizeUrl($c->filter($sel)->first()->attr('src'));
            }
        }
        return null;
    }

    protected function getAdditionalImages(Crawler $c)
    {
        $images = [];
        $selectors = [
            '#gallery img',
            '.thumbs-list img',
            '.product-images img',
            '.more-views img',
            '.product-additional-images img',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                $c->filter($sel)->each(function (Crawler $node) use (&$images) {
                    $src = $node->attr('src') ?: $node->attr('data-src') ?: $node->attr('data-large');
                    if ($src) $images[] = $this->normalizeUrl($src);
                });
                if (count($images)) break;
            }
        }
        return array_values(array_unique(array_filter($images)));
    }

    protected function getPrice(Crawler $c)
    {
        // price (regular)
        $selectors = [
            'span[itemprop="price"]',
            '.product-price .price',
            '.price .regular-price',
            '.current-price:not(.discounted)',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                return $this->cleanPrice($c->filter($sel)->first()->text());
            }
        }
        return null;
    }
    
    protected function getSalePrice(Crawler $c)
    {
        $selectors = [
            '.regular-price',
            '.product-price .price--reduced',
            '.current-price .discount',
            '.price.sale',
            '.price .was',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                return $this->cleanPrice($c->filter($sel)->first()->text());
            }
        }
        // if not found, sometimes price and sale price share same element — caller can detect equality
        return null;
    }

    protected function getCategories(Crawler $c)
    {
        $cats = [];
        // breadcrumbs usually list categories
        $selectors = [
            '.breadcrumb li a',
            '.breadcrumbs li a',
            '.breadcrumb a',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                $c->filter($sel)->each(function (Crawler $node) use (&$cats) {
                    $text = trim($node->text());
                    if ($text && strtolower($text) !== 'home' && strtolower($text) !== 'αρχική') {
                        $cats[] = $text;
                    }
                });
                break;
            }
        }

        return array_values(array_unique($cats));
    }

    protected function getReference(Crawler $c)
    {
        $selectors = [
            'span[itemprop=sku]',
            '.sku',
            '.product-reference',
            '#product_reference',
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                return trim($c->filter($sel)->first()->text());
            }
        }
        return null;
    }

    protected function getColour(Crawler $c)
    {
        $colors = [];

        $c->filter('.product-variants .product-variants-item')->each(function (Crawler $node) use (&$colors) {
            $labelNode = $node->filter('.control-label');
            if (!$labelNode->count()) return;

            $label = trim(str_replace(':', '', $labelNode->text()));

            if (in_array(strtolower($label), ['colour', 'color', 'Χρώμα'])) {
                $node->filter('select option')->each(function (Crawler $opt) use (&$colors) {
                    $text = trim($opt->text());

                    
                        $colors[] = $text;
                    
                });
            }
        });

        return count($colors) ? implode(', ', array_unique($colors)) : null;
    }

    protected function getSize(Crawler $c)
    {
        $sizes = [];

        $c->filter('.product-variants .product-variants-item')->each(function (Crawler $node) use (&$sizes) {
            $labelNode = $node->filter('.control-label');
            if (!$labelNode->count()) return;

            $label = trim(str_replace(':', '', $labelNode->text()));

            if (in_array(strtolower($label), ['size', 'Μέγεθος', 'Πλάτος Καθίσματος'])) {
                $node->filter('select option')->each(function (Crawler $opt) use (&$sizes) {
                    $text = trim($opt->text());

                    
                        $sizes[] = $text;
                    
                });
            }
        });

        return count($sizes) ? implode(', ', array_unique($sizes)) : null;
    }

    protected function getStock(Crawler $c)
    {
        $selectors = [
            'span[itemprop=availability]',
            '.availability',
            '.stock',
            '.product-quantities',
            '.bon-stock-countdown-counter'
        ];

        foreach ($selectors as $sel) {
            if ($c->filter($sel)->count()) {
                $txt = trim($c->filter($sel)->first()->text());
                // crude parse: find numbers in the text
                if (preg_match('/\d+/', $txt, $m)) {
                    return (int) $m[0];
                }
                // common words: in stock
                if (stripos($txt, 'out of stock') !== false || stripos($txt, 'εξαντλημένο') !== false) {
                    return 0;
                }
                return null;
            }
        }
        return null;
    }

    protected function getFeatures(Crawler $c)
    {
        $attributes = [];

        // The features block exists inside: <section class="product-features">
        if ($c->filter('section.product-features dl.data-sheet')->count()) {

            $dl = $c->filter('section.product-features dl.data-sheet');

            $dt = $dl->filter('dt.name');
            $dd = $dl->filter('dd.value');

            // dt and dd pairs - ensure same count
            for ($i = 0; $i < $dt->count(); $i++) {

                $key = trim($dt->eq($i)->text());
                $value = trim($dd->eq($i)->text());

                if ($key && $value) {
                    $attributes[$key] = $value;
                }
            }
        }

        return $attributes;
    }

    protected function parseVariants(Crawler $c, string $baseLink): array
    {
        
        $variants = [];
        $token = null;
        if ($c->filter('input[name="token"]')->count()) {
            $token = $c->filter('input[name="token"]')->attr('value');
        }
        // dd($token);
        // Find all variant items dynamically
        $variantItems = $c->filter('.product-variants .product-variants-item');
        // dd($variantItems);
        if ($variantItems->count() === 0) {
            return $variants; // no variants
        }

        $colors = [];
        $sizes  = [];

        $variantItems->each(function (Crawler $node) use (&$colors, &$sizes) {
            $labelNode = $node->filter('.control-label');
            if (!$labelNode->count()) return;

            $label = trim(str_replace(':', '', $labelNode->text()));

            if (in_array(strtolower($label), ['colour', 'color', 'Χρώμα'])) {
                $node->filter('option')->each(function (Crawler $opt) use (&$colors) {
                    $colors[] = [
                        'id'   => $opt->attr('value'),
                        'name' => trim($opt->text())
                    ];
                });
            }

            if (in_array(strtolower($label), ['size', 'Μέγεθος', 'Πλάτος Καθίσματος'])) {
                $node->filter('option')->each(function (Crawler $opt) use (&$sizes) {
                    $sizes[] = [
                        'id'   => $opt->attr('value'),
                        'name' => trim($opt->text())
                    ];
                });
            }
        });
        // dd($colors);
        // Build variant combinations
        if (empty($sizes)) {
            foreach ($colors as $color) {
                // $stock = $this->getVariantStock($c, $size['id'], $color['id']);
                $variantUrl = $baseLink . "#/{$color['id']}--";
                // $stock = $this->getVariantStockAjax($baseLink, $size['id'], $color['id'], $token);
                // dump($stock);
                // dump($size);
                $variants[] = [
                    'variant_id' => $color['id'],
                    'link'       => $variantUrl,
                    'size'       => null,
                    'colour'     => $color['name'],
                    'colour_id'  => $color['id'],
                ];
            }
            return $variants; // no size info
        }

        foreach ($sizes as $size) {
            // If colors exist, create one variant per color
            if (!empty($colors)) {
                foreach ($colors as $color) {
                    // $stock = $this->getVariantStock($c, $size['id'], $color['id']);
                    $variantUrl = $baseLink . "#/{$color['id']}--/{$size['id']}--{$size['name']}";
                    // $stock = $this->getVariantStockAjax($baseLink, $size['id'], $color['id'], $token);
                    // dump($stock);
                    // dump($size);
                    $variants[] = [
                        'variant_id' => $size['id'],
                        'link'       => $variantUrl,
                        'size'       => $size['name'],
                        'colour'     => $color['name'],
                    ];
                }
            } else {
                // $stock = $this->getVariantStock($c, $size['id'], $color['id']);
                $variantUrl = $baseLink . "#/{$size['id']}--{$size['name']}";
                // $stock = $this->getVariantStockAjax($baseLink, $size['id'], $color['id'], $token);
                // dump($stock);
                $variants[] = [
                    'variant_id' => $size['id'],
                    'link'       => $variantUrl,
                    'size'       => $size['name'],
                    'colour'     => null,
                ];
            }
        }
        // foreach ($variants as $i => $variant) {
            
        //     try {
        //         // $variantCrawler = $this->client->request('GET', $variant['link']);
        //         $stock = $this->getVariantStock($c);
        //         // dd($stock);
        //     } catch (\Exception $e) {
        //         $stock = null;
        //     }

        //     $variants[$i]['stock'] = $stock;
        // }
        // dd($variants);
        return $variants;
    }


    protected function getVariantStockAjax(string $productLink, $sizeId, $colorId = null, string $token): ?int
    {
        try {
            
            // $url = 'https://koinis.gr/el/index.php?' . http_build_query($query);
            // dump($url);
            $response = Http::get('https://koinis.gr/el/index.php?', [
                'controller' => 'product',
                'id_product' => $this->extractProductIdFromLink($productLink),
                'ajax'       => 1,
                'action'     => 'refresh',
                'id_product_attribute' => $sizeId,
                'qty' => 1,
                'token' => $token,
            ]);

            // dd($response->body());
            // $data = $response->json();
            // dump($data);
            $html = $response->body();
            $crawler = new Crawler($html);
            // dd($crawler);
            // Grab stock from Prestashop response
            $stock = null;
            $crawler->filter('span.bon-stock-countdown-counter')->each(function (Crawler $node) use (&$stock) {
                $value = $node->attr('data-value');
                dd($value);
                if ($value !== null && is_numeric($value)) {
                    $stock = (int) $value;
                } else {
                    $txt = trim($node->text());
                    dump($txt);
                    if (preg_match('/\d+/', $txt, $matches)) {
                        $stock = (int) $matches[0];
                    }
                }
            });

            return $stock;
        } catch (\Exception $e) {
            dd($e);
            return null;
        }
    }

    /**
     * Extract product ID from the product URL
     */
    protected function extractProductIdFromLink(string $link)
    {
        // assuming URL like https://koinis.gr/el/product-name/123-product-slug
        if (preg_match('/\/(\d+)-[^\/]+$/', $link, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function getVariantStock(Crawler $c)
    {
        // Find the node that matches this size/color combination
        // $selector = '.bon-stock-countdown-counter'; // adjust if needed per variant
        // $stock = null;
        // $c->filter($selector)->each(function (Crawler $node) use (&$stock) {
        //     $txt = trim($node->text());

        //     if (preg_match('/\d+/', $txt, $m)) {
        //         $stock = (int) $m[0];
        //     }
        //     if (stripos($txt, 'out of stock') !== false || stripos($txt, 'εξαντλημένο') !== false) {
        //         $stock = 0;
        //     }
        // });

        // return $stock;
        $stock = null;

        // Select the span with stock
        $c->filter('span.bon-stock-countdown-counter')->each(function (Crawler $node) use (&$stock) {
            // Prefer data-value attribute
            $value = $node->attr('data-value');

            if ($value !== null && is_numeric($value)) {
                $stock = (int) $value;
            } else {
                // Fallback: get text content
                $txt = trim($node->text());
                if (preg_match('/\d+/', $txt, $matches)) {
                    $stock = (int) $matches[0];
                }
            }
        });

        return $stock;
    }

    protected function cleanPrice($raw)
    {
        if (!$raw) return null;
        $raw = str_replace("\xc2\xa0", ' ', $raw);

        $raw = trim($raw);

        $raw = preg_replace('/\s+/', ' ', $raw);
        $raw = str_replace(',', '.', $raw);
        return $raw;
    }

    protected function normalizeUrl($url)
    {
        // return absolute URL if relative
        $url = trim($url);
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        if (strpos($url, 'http') === 0) {
            return $url;
        }

        // if relative, prefix base domain (koinis.gr)
        return rtrim('https://koinis.gr', '/') . '/' . ltrim($url, '/');
    }   
}
