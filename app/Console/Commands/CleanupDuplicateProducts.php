<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateProducts extends Command
{
    protected $signature = 'products:cleanup-duplicates
                            {--shop-url=www.mobilesentrix.co.uk : Restrict cleanup to a single shop_url}
                            {--keep=oldest : Which duplicate to keep (oldest or latest)}
                            {--dry-run : Preview duplicate cleanup without deleting rows}';

    protected $description = 'Remove duplicate products, keeping one row per identity group';

    public function handle(): int
    {
        $shopUrl = $this->option('shop-url');
        $keep = strtolower((string) $this->option('keep'));
        $dryRun = (bool) $this->option('dry-run');

        if (!in_array($keep, ['oldest', 'latest'], true)) {
            $this->error('Invalid --keep value. Use oldest or latest.');

            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Scanning duplicates for %s (%s)...',
            $shopUrl ?: 'all shops',
            $dryRun ? 'dry run' : 'execute'
        ));

        $summary = [
            'groups' => 0,
            'rows_to_delete' => 0,
            'deleted' => 0,
        ];

        $skuGroups = $this->findDuplicateGroups('sku', $shopUrl);
        $linkGroups = $this->findDuplicateGroups('link', $shopUrl, true);
        $groups = [...$skuGroups, ...$linkGroups];

        if (empty($groups)) {
            $this->info('No duplicate products found.');

            return Command::SUCCESS;
        }

        $deleteIds = [];
        $rows = [];

        foreach ($groups as $group) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $group->ids))));

            if (count($ids) < 2) {
                continue;
            }

            if ($keep === 'latest') {
                rsort($ids, SORT_NUMERIC);
            } else {
                sort($ids, SORT_NUMERIC);
            }

            $keepId = array_shift($ids);

            $rows[] = [
                'type' => $group->type,
                'shop_url' => $group->shop_url,
                'value' => $group->value,
                'keep_id' => $keepId,
                'delete_ids' => implode(', ', $ids),
                'count' => $group->total,
            ];

            $deleteIds = array_merge($deleteIds, $ids);
        }

        $summary['groups'] = count($rows);
        $summary['rows_to_delete'] = count($deleteIds);

        $this->table(
            ['Type', 'Shop URL', 'Value', 'Keep ID', 'Delete IDs', 'Count'],
            array_slice($rows, 0, 20)
        );

        if (count($rows) > 20) {
            $this->line('Showing first 20 duplicate groups only.');
        }

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run complete. %d groups found, %d rows would be deleted.',
                $summary['groups'],
                $summary['rows_to_delete']
            ));

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($deleteIds, &$summary) {
            foreach (array_chunk($deleteIds, 500) as $chunk) {
                $deleted = Product::query()->whereIn('id', $chunk)->delete();
                $summary['deleted'] += $deleted;
            }
        });

        $this->info(sprintf(
            'Cleanup complete. %d groups processed, %d duplicate rows deleted.',
            $summary['groups'],
            $summary['deleted']
        ));

        return Command::SUCCESS;
    }

    protected function findDuplicateGroups(string $column, ?string $shopUrl = null, bool $onlyNullSku = false): array
    {
        $query = Product::query()
            ->selectRaw('? as type, shop_url, ' . $column . ' as value, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as total', [$column])
            ->whereNotNull($column)
            ->groupBy('shop_url', $column)
            ->havingRaw('COUNT(*) > 1');

        if ($shopUrl) {
            $query->where('shop_url', $shopUrl);
        }

        if ($onlyNullSku) {
            $query->whereNull('sku');
        } elseif ($column === 'sku') {
            $query->whereNotNull('sku');
        }

        return $query->get()->all();
    }
}