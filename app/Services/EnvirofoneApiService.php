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
    public function fetchFeed(string $format = 'csv', int $limit = -1): string
    {
        $response = Http::withHeaders([
            'RS-Key'    => config('services.envirofone.key'),
            'RS-Secret' => config('services.envirofone.secret'),
        ])
        ->timeout(120) // increase from 30s → 120s
        ->connectTimeout(20)
        ->get($this->baseUrl, [
            'format' => $format,
            'limit'  => $limit,
        ]);

        if ($response->failed()) {
            $this->logger->error('Envirofone API fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Envirofone API returned status {$response->status()}");
        }

        return (string)$response->body();
    }
}
