<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FeedSyncService;

class SyncProductFeeds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feeds:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(FeedSyncService $service)
    {
        $this->info('Starting feed sync...');

        $service->run();

        $this->info('Feed sync completed successfully.');

        return Command::SUCCESS;
    }
}
