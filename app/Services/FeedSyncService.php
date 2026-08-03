<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FeedSyncService
{
    protected string $staticFeedPath = 'feeds/698_1059_GoogleUS.txt';
    protected string $newUrl    = 'feeds/qchrym-yj.myshopify.com_products.txt';

    public function run(): void
    {
        // Ensure directory exists
        Storage::makeDirectory('feeds');

        $staticPath = Storage::path($this->staticFeedPath);
        $newPath    = Storage::path($this->newUrl);
        $finalPath  = Storage::path('feeds/qchrym-yj.myshopify.com_final_1.txt');

        // 1. Download feeds using system curl
        // $this->downloadViaCurl($this->staticUrl, $staticPath);
        // $this->downloadViaCurl($this->newUrl, $newPath);

        // 2. Build MPN => ID map from static feed
        $staticMpnMap = $this->buildStaticMpnIndex($staticPath);

        // 3. Generate final feed
        $this->generateFinalFeed($newPath, $finalPath, $staticMpnMap);
    }

    /**
     * Download large file using system curl (no PHP timeout limits)
     */
    protected function downloadViaCurl(string $url, string $outputPath): void
    {
        Http::timeout(240)
            ->retry(3, 4000)
            ->sink($outputPath)
            ->get($url)
            ->throw();
    }

    /**
     * Build MPN => ID map from static feed
     */
    protected function buildStaticMpnIndex(string $path): array
    {
        $file = new \SplFileObject($path);
        $file->setCsvControl("\t");
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $headers = $file->fgetcsv();

        $mpnIndex = array_search('mpn', $headers, true);
        $idIndex  = array_search('id', $headers, true);

        if ($mpnIndex === false || $idIndex === false) {
            throw new \RuntimeException('mpn or product_id  column missing in static feed');
        }

        $map = [];

        foreach ($file as $row) {
            if (!isset($row[$mpnIndex], $row[$idIndex])) {
                continue;
            }

            $mpn = $this->normalize($row[$mpnIndex]);
            if ($mpn) {
                $map[$mpn] = $row[$idIndex];
            }
        }

        return $map;
    }

    /**
     * Stream new feed and write final feed
     */
    protected function generateFinalFeed(
        string $newFeedPath,
        string $outputPath,
        array $staticMpnMap
    ): void {
        $input = new \SplFileObject($newFeedPath);
        $input->setCsvControl("\t");
        $input->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $output = new \SplFileObject($outputPath, 'w');

        $headers = $input->fgetcsv();
        $output->fputcsv($headers, "\t");

        $gtinIndex = array_search('gtin', $headers, true);
        $idIndex  = array_search('product_id', $headers, true);

        foreach ($input as $row) {
            if (!isset($row[$gtinIndex])) {
                continue;
            }

            $gtin = $this->normalize($row[$gtinIndex]);

            // If match found → overwrite with OLD product_id
            if ($gtin && isset($staticMpnMap[$gtin])) {
                $row[$idIndex] = $staticMpnMap[$gtin];
            }

            // Else → keep NEW product_id as-is
            $output->fputcsv($row, "\t");
        }
    }

    protected function normalize(?string $value): ?string
    {
        return $value ? strtolower(trim($value)) : null;
    }
}
