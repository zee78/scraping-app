<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HeadlaneProductService;

class SyncHeadlaneProducts extends Command
{
    protected $signature = 'headlane:sync-products';
    protected $description = 'Fetch Headlane products, store them and export CSV';

    protected HeadlaneProductService $service;

    // public function __construct(HeadlaneProductService $service)
    // {
    //     parent::__construct();
    //     $this->service = $service;
    // }

    public function handle(HeadlaneProductService $service): int
    {
        try {
            $this->info('Sync started...');
            $service->sync();
            $this->info('Sync completed successfully.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
