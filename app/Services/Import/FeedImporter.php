<?php

namespace App\Services\Import;

use App\Repositories\ProductRepository;
use App\Services\Mapper\EnvirofoneCsvMapper;
use Psr\Log\LoggerInterface;

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

        $rows = array_map('str_getcsv', $lines);

        // Normalize header keys (preserve as given)
        $header = array_shift($rows);
        $count = 0;

        foreach ($rows as $row) {
            // skip empty rows
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            // If CSV columns mismatch, try to pad or skip
            if (count($row) !== count($header)) {
                $this->logger->warning('CSV row column count mismatch; skipping', ['row' => $row]);
                continue;
            }

            $assoc = array_combine($header, $row);

            // map to internal shape
            $mapped = EnvirofoneCsvMapper::map($assoc);

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
}
