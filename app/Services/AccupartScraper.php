<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Product;

class AccupartScraper
{
    protected string $baseUrl = 'https://www.accupart.co.uk';

    public function scrapeAllProducts(): void
    {
        $collections = $this->getCollections();

        foreach ($collections as $collection) {
            $this->scrapeCollection($collection['handle']);
        }
    }

    protected function getCollections(): array
    {
        $response = Http::get($this->baseUrl . '/collections.json');

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch collections');
        }

        return $response->json()['collections'] ?? [];
    }

    protected function scrapeCollection(string $handle): void
    {
        $page = 1;

        do {
            $url = "{$this->baseUrl}/collections/{$handle}/products.json?page={$page}&limit=250";

            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                break;
            }

            $products = $response->json()['products'] ?? [];

            foreach ($products as $product) {
                dump($product);
                $this->storeProduct($product);
            }

            $page++;

            usleep(rand(200000, 500000)); // 200–500ms delay

        } while (!empty($products));
    }

    protected function mapProductVariants(array $product): array
    {
        
        $mapped = [];
        
        $mainImage = $product['images'][0]['src'] ?? null;

        $additionalImages = [];
        foreach ($product['images'] as $index => $image) {
            if ($index === 0) continue;
            $additionalImages[] = $image['src'];
        }

        foreach ($product['variants'] as $variant) {
            $actualPrice = (float) $variant['price'];
            $comparePrice = $variant['compare_at_price']
                ? (float) $variant['compare_at_price']
                : null;

            if ($comparePrice && $comparePrice > $actualPrice) {
                $price = $comparePrice;
                $salePrice = $actualPrice;
            } else {
                $price = $actualPrice;
                $salePrice = null;
            }

            $name = $variant['title'] === 'Default Title'
                ? $product['title']
                : $product['title'] . ' - ' . $variant['title'];

            $mapped[] = [
                'product_id'   => $product['id'],
                'variant_id'   => $variant['id'],
                'sku'          => $variant['sku'],
                'title'         => $name,
                'price'        => $price,
                'sale_price'   => $salePrice,
                'shop_url' => 'www.accupart.co.uk',
                // 'availability' => $variant['available'] ? 'In Stock' : 'Out of Stock',
                'stock'        => $variant['available'] ? 1 : 0,
                'link'         => $this->baseUrl . '/products/' . $product['handle'],
                'raw' => json_encode($variant),
                'image' => $mainImage,
                'additional_images' => json_encode($additionalImages),
            ];
        }

        return $mapped;
}

    protected function storeProduct(array $product): void
    {
      $variants = $this->mapProductVariants($product);
      
      foreach ($variants as $data) {
        // dump($data); // DEBUGGING: Check the mapped data before saving
        //   Product::updateOrCreate(
        //       [
        //           'variant_id' => $data['variant_id'], // 🔑 UNIQUE KEY
        //       ],
        //       $data
        //   );
      }
    }

    public function exportToCsv(): void
    {
        $fileName = 'accupart-products.csv';
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
        $query = Product::where('shop_url', 'www.accupart.co.uk');

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
