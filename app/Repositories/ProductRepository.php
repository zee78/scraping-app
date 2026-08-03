<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Attribute;
use App\Helpers\ProductIdGenerator;

class ProductRepository
{
    public function storeOrUpdate(array $data): Product
    {           
        // dd($data);
        $items = !empty($data['variants']) && is_array($data['variants']) 
        ? $data['variants'] 
        : [$data];

        $lastProduct = null;

        foreach ($items as $item) {

            $shopUrl = null;
            $shopName = null;
            if (!empty($item['link'] ?? $data['link'])) {
                $fullLink = $item['link'] ?? $data['link'];

                // Parse domain
                $parsed = parse_url($fullLink);
                if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                    $shopUrl = $parsed['scheme'] . '://' . $parsed['host']; 
                    $shopName = $parsed['host'];
                }
            }
            
            // dd($shopName, 'Url:', $shopUrl);
            // dd($item);
            $payload = [
                'source_id' => $data['source_id'] ?? null,
                'shop_url' => $shopName ?? null,
                // 'sku' => $item['size'] ? $data['sku'] . '-' . $item['size'] :  $data['sku'],
                'sku' => !empty($item['size'])
                    ? $data['sku'] . '-' . $item['size']
                    : (!empty($item['colour_id'])
                        ? $data['sku'] . '-' . $item['colour_id']
                        : $data['sku']),
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'link' => $item['link'] ?? $data['link'] ?? null,
                'main_image' => $data['image_link'] ?? $data['main_image'] ?? null,
                'additional_images' => $data['additional_images'] ?? [],
                'price' => isset($data['price']) ? $data['price'] : null,
                'sale_price' => isset($data['sale_price']) ? $data['sale_price'] : null,
                'reference' => $data['reference_number'] ?? ($data['reference'] ?? null),
                'colour' => $item['colour'] ?? $item['color'] ?? $data['colour'] ?? null,
                'size' => $item['size'] ?? null,
                'stock' => isset($data['stock']) ? (int) $data['stock'] : null,
                'categories' => $data['categories'] ?? [],
                'variant_id' => $item['variant_id'] ?? null,
                'raw' => $data,
            ];

            // prefer link as unique key; fallback to source_id
            $query = Product::query();

            if($shopName === 'www.envirofone.com'){
                if (!empty($payload['sku'])) {
                    $query->where('sku', $payload['sku']);
                }
            } else {
                if (!empty($payload['variant_id'])) {
                    $query->where('variant_id', $payload['variant_id'])->where('link', $item['link']);
                } elseif (!empty($payload['link'])) {
                    $query->where('link', $payload['link'])->where('sku', $payload['sku']);
                } elseif (!empty($payload['source_id'])) {
                    $query->where('source_id', $payload['source_id']);
                }
            }

            $product = $query->first();
            // dump('Updating product:', $product);
            if ($product) {
                $product->update($payload);
            } else {
                $product = Product::create($payload);
            }

            if (!empty($data['attributes']) && is_array($data['attributes'])) {

                // DELETE old attributes (clean sync)
                Attribute::where('product_id', $product->id)->delete();

                // Insert new attributes
                foreach ($data['attributes'] as $key => $value) {
                    Attribute::create([
                        'product_id' => $product->id,
                        'key'        => trim($key),
                        'value'      => trim($value),
                    ]);
                }
            }

            // save the scraped URL list to storage (for import/export)
            $this->appendUrlToIndex($payload['link'] ?? null);
            $lastProduct = $product;
        }
        return $lastProduct;
    }

    protected function appendUrlToIndex(?string $url): void
    {
        if (empty($url)) return;

        $path = storage_path('app/koinis_urls.json');

        $urls = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $urls = json_decode($content, true) ?: [];
        }

        if (!in_array($url, $urls)) {
            $urls[] = $url;
            file_put_contents($path, json_encode(array_values($urls), JSON_PRETTY_PRINT));
        }
    }

    protected function normalizeFloat($value)
    {
        if ($value === null) return null;
        // remove non-numeric except dot and comma
        $value = trim($value);
        // $value = str_replace(['€', '$', '£', ' '], '', $value);
        // replace comma decimals with dot if needed
        if (substr_count($value, ',') > 0 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return is_numeric($value) ? (float) $value : null;
    }

    public function conditionalExportCsv(): string
    {
        $exportDir = storage_path('app/');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        $envirofoneFile = $exportDir . 'envirofone_products.csv';
        $this->exportCsv('envirofone', $envirofoneFile);

        // 2. Export all OTHER shops separately
        $otherShops = Product::where('shop_url', '!=', 'www.envirofone.com')
            ->orWhereNull('shop_url')
            ->select('shop_url')
            ->distinct()
            ->pluck('shop_url');

        $fileList = ["Envirofone File: {$envirofoneFile}"];

        foreach ($otherShops as $shopUrl) {

            $fileKey = 'koinis';

            $fileName = $fileKey.'_products.csv';
            $filePath = "{$exportDir}/{$fileName}";

            $this->exportCsv($shopUrl, $filePath);

            $fileList[] = "Shop: {$fileKey} -> {$filePath}";
        }

        return "CSV Export Complete:\n" . implode("\n", $fileList);
    }

    public function exportCsv(?string $shopUrl, string $filePath): string
    {
        // $filePath = storage_path('app/' . $fileName);
        // $products = Product::all();
        $query = Product::query()->where('link', 'not like', 'https://koinis.gr/en/home/%');

        if ($shopUrl === 'envirofone') {
            $query->where('shop_url', 'www.envirofone.com');
        } else {
            $query->where('shop_url', $shopUrl)->whereNull('shop_url');
        }

        $products = $query->get();


        if ($products->isEmpty()) {
            return "No products found to export.";
        }
        // dd($filePath);
        $handle = fopen($filePath, 'w');

        // CSV header
        fputcsv($handle, [
            'Product ID','Title', 'Description', 'Link', 'Main Image', 'Additional Images',
            'Price', 'Sale Price', 'Reference', 'Colour', 'Size', 'Stock', 'Categories', 'Availability', 'Master Category'
        ]);

        foreach ($products as $product) {

            if($shopUrl !== 'envirofone'){
                if (empty($product->stock) || $product->stock <= 0) {
                    continue;
                }
            }

            $googleCategory = !empty($product->categories)
            ? implode(' > ', $product->categories)
            : '';

            fputcsv($handle, [
                $product->sku ?? '',
                $product->title ?? '',
                $product->description ?? '',
                $product->link ?? '',
                $product->main_image ?? '',
                implode(', ', $product->additional_images ?? []),
                $product->sale_price ?? '',
                $product->price ?? '',
                $product->reference ?? '',
                $product->colour ?? '',
                $product->size ?? '',
                $product->stock ?? '',
                implode(', ', $product->categories ?? []),
                'in stock',
                $googleCategory
            ]);
        }

        fclose($handle);

        return "CSV exported successfully to $filePath";
    }
}
