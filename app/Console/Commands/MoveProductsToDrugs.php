<?php

namespace App\Console\Commands;

use App\Services\CatalogueMoveService;
use Illuminate\Console\Command;

class MoveProductsToDrugs extends Command
{
    protected $signature = 'pmas:move-products-to-drugs
                            {--apply : Actually move the rows; without this the command only lists them}';

    protected $description = 'Move medical supplies, vitamins and infant formula from Products to Drugs, keeping stock and sales history.';

    public function handle(CatalogueMoveService $service): int
    {
        $rows = $service->preview();

        if ($rows === []) {
            $this->info('Nothing to move: no medical supplies, vitamins or infant formula are on the Products side.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'SKU', 'From category', 'Type', 'Packing', 'Sells', 'Min', 'Stock', 'Expired', 'Batches', 'Sales', 'Clash'],
            array_map(fn (array $row) => [
                $row['id'],
                $row['name'],
                $row['sku'] ?? '-',
                $row['category'],
                $row['kind'],
                $row['packing'],
                $row['sells'],
                $row['minimum'],
                $row['stock'],
                $row['expired'],
                $row['batches'],
                $row['sales'],
                $row['clash'] ?? '-',
            ], $rows)
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Nothing has been changed. Re-run with --apply to move these rows.');

            return self::SUCCESS;
        }

        $summary = $service->apply();

        $this->info(sprintf(
            'Moved %d new drug(s), merged %d into existing drug(s), skipped %d.',
            $summary['moved'],
            $summary['merged'],
            $summary['skipped']
        ));

        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
