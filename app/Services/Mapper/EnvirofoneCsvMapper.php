<?php

namespace App\Services\Mapper;
use App\Helpers\ProductIdGenerator;

class EnvirofoneCsvMapper
{
    public static function map(array $row): array
    {
        dump('rowwwww as Row', $row);
        // $sku = 'EF-' . ($row['variant id'] ?? $row['Variant ID'] ?? null) . '-' . ($row['Colour']  ?? null) . '-' . ($row['Condition'] ?? null);
        $variantId = $row['Variant ID']
            ?? $row['variant_id']
            ?? $row['variant id']
            ?? null;

        $colour = $row['Colour'] ?? null;
        $condition = $row['Condition'] ?? null;

        // Normalize values
        $variantId = trim((string) $variantId);
        $colour = $colour ? strtoupper(trim($colour)) : 'NA';
        $condition = $condition ? strtoupper(trim($condition)) : 'NA';

        // If variant ID missing → skip product
        if (!$variantId) {
            throw new \Exception('Missing variant ID');
        }

        $sku = sprintf('EF-%s-%s-%s', $variantId, $colour, $condition);
        dump('Generated SKU:', $sku);
        $link = $row['Deep Link'] ?? ($row['deep_link'] ?? null);
        $parsed = $link ? parse_url($link) : [];
        $shopUrl = (!empty($parsed['scheme']) && !empty($parsed['host'])) ? ($parsed['scheme'].'://'.$parsed['host']) : null;
        $shopName = $parsed['host'] ?? null;
        // dd($row['Condition']);
        return [
            // create a stable source id (optional)
            'source_id' => $link ? md5($link) : null,

            // sku generation (simple)
            'sku' => $sku,

            'title' => $row['Title'] ?? null,
            'description' => $row['Description'] ?? null,

            'link' => $link,
            'shop_url' => $shopUrl,
            'shop_name' => $shopName,

            'main_image' => $row['Image URL'] ?? null,
            'additional_images' => [],

            'price' => isset($row['Original Price']) ? (float) $row['Original Price'] : null,
            'sale_price' => $row['Sale Price'] ? (float) $row['Sale Price'] : null,

            'reference' => null,
            'colour' => $row['Colour'] ?? null,
            'size' => $row['Capacity'] ?? null,
            'stock' => isset($row['Quantity in Stock']) ? (int) $row['Quantity in Stock'] : null,
            'categories' => [],

            'variants' => $row['Variant ID'] ?? null,
            'variant_id' => $row['Variant ID'] ?? null,

            'attributes' => [
                'make' => $row['Make'] ?? null,
                'version' => $row['Version'] ?? null,
                'condition' => $row['Condition'] ?? null,
            ],

            'raw' => $row,
        ];
    }
}
