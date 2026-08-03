<?php

namespace App\Services;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HeadlaneProductService
{
    protected string $apiUrl;
    protected string $apiKey;
    /**
     * Login to Headlane shop using Auth0
     */
    public function __construct()
    {
        $this->apiKey = config('services.headlane.api_key');
        $this->apiUrl = config('services.headlane.api_url');
    }

    public function fetchProducts(): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept'    => 'application/json',
        ])
        ->timeout(120)      // ⬅️ give it more time
        ->retry(3, 2000)->get($this->apiUrl);

        if ($response->failed()) {
            throw new \Exception('Headlane API request failed');
        }

        return $response->json();
    }

    public function storeProducts(array $products): void
    {
        // foreach ($products as $item) {
        //     Product::updateOrCreate(
        //         ['sku' => $item['Stock_Code']],
        //         [
        //             'shop_url'    => 'shop.headlane.co.uk',
        //             'title'        => $item['Name'],
        //             'nl_stock'    => $item['NL'],
        //             'uk_stock'    => $item['UK'],
        //             'price'   => $item['GBP'],
        //             'link' => $item['product_url'],
        //             'raw' => $item,
        //         ]
        //     );
        // }
        $now = now();

    collect($products)->chunk(1000)->each(function ($chunk) use ($now) {
        $rows = [];

        foreach ($chunk as $item) {
            $rows[] = [
                'sku'        => $item['Stock_Code'],
                'shop_url'   => 'shop.headlane.co.uk',
                'title'      => $item['Name'],
                'nl_stock'   => $item['NL'],
                'uk_stock'   => $item['UK'],
                'price'      => $item['GBP'],
                'link'       => $item['product_url'],
                'raw'        => json_encode($item),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('products')->upsert(
            $rows,
            ['sku'], // unique key
            [
                'shop_url',
                'title',
                'nl_stock',
                'uk_stock',
                'price',
                'link',
                'raw',
                'updated_at',
            ]
        );
    });
    }

    public function exportToCsv(): string
    {
        $fileName = 'headlane_products.csv';
        $path = storage_path("app/public/{$fileName}");

        $handle = fopen($path, 'w');

        fputcsv($handle, [
            'Product ID',
            'Name',
            'NL Stock',
            'UK Stock',
            'Price',
            'Link',
            'Availability',
        ]);

        Product::where('shop_url', 'shop.headlane.co.uk')
        ->chunk(500, function ($products) use ($handle) {
            foreach ($products as $product) {
                fputcsv($handle, [
                    $product->sku,
                    $product->title,
                    $product->nl_stock,
                    $product->uk_stock,
                    $product->price,
                    $product->link,
                    ($product->nl_stock > 0 || $product->uk_stock > 0) ? 'in_stock' : 'Out of Stock',
                ]);
            }
        });

        fclose($handle);

        return $fileName;
    }

    public function sync(): void
    {
        $products = $this->fetchProducts();
        $this->storeProducts($products);
        $this->exportToCsv();
    }
}
