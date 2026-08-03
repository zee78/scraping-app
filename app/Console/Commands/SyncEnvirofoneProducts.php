<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnvirofoneApiService;
use App\Services\Import\FeedImporter;
use Psr\Log\LoggerInterface;
use App\Repositories\ProductRepository;

class SyncEnvirofoneProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-envirofone {--format=csv} {--limit=1000}';
    protected $description = 'Sync products from Envirofone API into local database';
    protected ProductRepository $repo;

    /**
     * The console command description.
     *
     * @var string
     */
    protected EnvirofoneApiService $apiService;
    protected FeedImporter $importer;
    protected LoggerInterface $logger;
    protected array $makes = [
        // 'Apple',
        // 'Samsung',
        'Sony',
        // 'OnePlus',
        // 'Google',
        // 'Huawei',
        // 'Motorola',
    ];
    /**
     * Execute the console command.
     */

    public function __construct(EnvirofoneApiService $apiService, FeedImporter $importer, LoggerInterface $logger, ProductRepository $repo)
    {
        parent::__construct();
        $this->apiService = $apiService;
        $this->importer = $importer;
        $this->logger = $logger;
        $this->repo = $repo;
    }

    public function handle()
    {
        $format = $this->option('format');
        $limit = (int)$this->option('limit');

        $this->info("Starting Envirofone sync (format={$format}, limit={$limit})");

        try {

            foreach ($this->makes as $make) { 
                $body = $this->apiService->fetchFeed($format, $limit, null, $make, true);

                $this->info('Feed fetched — starting import.');

                $importedCount = 0;

                if ($format === 'xml') {
                    $importedCount = $this->importer->importXmlString($body);
                } else {
                    $importedCount = $this->importer->importCsvString($body);
                    // for xml you would add an importXmlString method in FeedImporter
                    // $this->error('Unsupported format for import: ' . $format);
                    // return 1;
                }

                $this->info("Import finished. Products imported: {$importedCount}");
                $this->logger->info('Envirofone sync completed', ['imported' => $importedCount]);
            }
            $this->repo->conditionalExportCsv();

            $this->info("CSV exported successfully: ");
            return 0;
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());
            $this->logger->error('Envirofone sync error', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
