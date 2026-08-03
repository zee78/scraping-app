<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

class EnvirofoneApiService
{
    protected string $baseUrl;
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->baseUrl = config('services.envirofone.url');
        $this->logger = $logger;
    }

    /**
     * Fetch raw feed body from remote API.
     * $format: 'csv' or 'xml'
     */
    public function fetchFeed(
        string $format = 'csv', 
        int $limit = -1, 
        ?string $productType = null,
        ?string $make = null,
        ?bool $inStock = null
    ): string {
        $params = array_filter([
            'data-experts' => config('services.envirofone.data_exerts_key'),
            'format'       => strtoupper($format),
            'limit'        => $limit > 0 ? $limit : null,
            'product_type' => $productType,
            'make'         => $make,
            'in_stock'     => $inStock ? 'true' : null,
        ]);
        $response = Http::timeout(300)
        ->connectTimeout(120)
        ->retry(3, 5000)
        ->timeout(240)
        ->get($this->baseUrl, $params);

        if ($response->failed()) {
            $this->logger->error('Envirofone API fetch failed', [
                'status' => $response->status(),
                'params' => $params,
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Envirofone API returned status {$response->status()}");
        }

        return (string)$response->body();
    }
}
