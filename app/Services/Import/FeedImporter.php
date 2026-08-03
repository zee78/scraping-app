<?php

namespace App\Services\Import;

use App\Repositories\ProductRepository;
use App\Services\Mapper\EnvirofoneCsvMapper;
use Psr\Log\LoggerInterface;
use App\Helpers\ProductIdGenerator;

class FeedImporter
{
    protected ProductRepository $repo;
    protected LoggerInterface $logger;

    public function __construct(ProductRepository $repo, LoggerInterface $logger)
    {
        $this->repo = $repo;
        $this->logger = $logger;
    }

    /**
     * Import CSV content (string) — parse and persist using your repository.
     */
    public function importCsvString(string $csvContent): int
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csvContent));
        if (empty($lines)) {
            return 0;
        }
        dump('CSV Lines Count:', count($lines));
        $rows = array_map('str_getcsv', $lines);
        dump('Parsed CSV Rows:', $rows);

        // Normalize header keys (preserve as given)
        $header = array_shift($rows);
        $count = 0;

        foreach ($rows as $row) {
            // skip empty rows
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                dump('skipping empty row', $row);
                continue;
            }

            // If CSV columns mismatch, try to pad or skip
            if (count($row) !== count($header)) {
                $this->logger->warning('CSV row column count mismatch; skipping', ['row' => $row]);
                continue;
            }
            dump('Processing CSV Row:', $row);
            dump('heder', $header);
            $assoc = array_combine($header, $row);
            dump('Associative Row:', $assoc);
            // map to internal shape
            $mapped = EnvirofoneCsvMapper::map($assoc);
            // dd($mapped);
            // persist
            try {
                $this->repo->storeOrUpdate($mapped);
                $count++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to store product', [
                    'error' => $e->getMessage(),
                    'product' => $mapped['raw'] ?? null,
                ]);
            }
        }

        return $count;
    }

    public function importXmlString(string $xmlContent): int
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlContent);

        if (!$xml) {
            $this->logger->error('Invalid XML feed');
            return 0;
        }

        $count = 0;
        $processed = [];

        foreach ($xml->product as $product) {

            $variantId = trim((string) $product->variant_id);
            $capacity = strtoupper(trim((string) $product->capacity));
            $colour = strtoupper(trim((string) $product->colour));
            $condition = strtoupper(trim((string) $product->condition));

            if (!$variantId || !$condition) {
                continue; // skip invalid
            }

            // Prevent duplicate processing inside same feed
            $uniqueKey = $variantId . '-' . $capacity . '-' . $colour . '-' . $condition;
            $sku = ProductIdGenerator::generate(
                $variantId ?? null,
                $capacity ?? null,
                $colour ?? null,
                $condition ?? null,
                $capacity ?? null,
            );
            dump('Generated SKU:', $sku); die();
             if (!$sku) {
                continue; // skip if SKU generation fails
            }

             if (isset($processed[$sku])) {
                continue; // skip duplicates in feed
            }

            if (isset($processed[$uniqueKey])) {
                continue;
            }

            $processed[$uniqueKey] = true;

            $mapped = [
                'source_id' => (string) $product->deep_link ? md5((string) $product->deep_link) : null,
                'sku' => 'EF-' . $uniqueKey,
                'variant_id' => $variantId,
                'title' => (string) $product->title,
                'description' => (string) $product->description,
                'link' => (string) $product->deep_link,
                'main_image' => (string) $product->image_url,
                'price' => (float) $product->original_price,
                'sale_price' => (float) $product->sale_price,
                'colour' => (string) $product->colour,
                'size' => (string) $product->capacity,
                'stock' => (int) $product->quantity_in_stock,
                'attributes' => [
                    'make' => (string) $product->make,
                    'version' => (string) $product->version,
                    'condition' => $condition,
                ],
                'raw' => json_decode(json_encode($product), true),
            ];

            try {
                $this->repo->storeOrUpdate($mapped);
                $count++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to store XML product', [
                    'error' => $e->getMessage(),
                    'sku' => $mapped['sku'],
                ]);
            }
        }

        return $count;
    }
}
