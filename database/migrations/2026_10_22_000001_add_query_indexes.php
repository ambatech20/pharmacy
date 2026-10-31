<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the queries the screens run most often.
 *
 * Single-column foreign-key indexes already exist; these cover the second
 * column in the filter (expiry, date, name) so MySQL can answer the stock,
 * alert, sales and purchase lists from one index instead of scanning.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string, 1:array<int,string>, 2:string}> */
    private array $indexes = [
        ['purchase_batches', ['branch_id', 'expiry_date', 'quantity_remaining'], 'pb_branch_expiry_qty_idx'],
        ['purchase_batches', ['drug_id', 'expiry_date'], 'pb_drug_expiry_idx'],
        ['purchase_batches', ['product_id', 'expiry_date'], 'pb_product_expiry_idx'],
        ['purchase_batches', ['branch_id', 'purchase_date'], 'pb_branch_purchase_idx'],
        ['drugs', ['name'], 'drugs_name_idx'],
        ['drugs', ['category_id', 'name'], 'drugs_category_name_idx'],
        ['drugs', ['kind'], 'drugs_kind_idx'],
        ['products', ['active', 'name'], 'products_active_name_idx'],
        ['products', ['category_id', 'name'], 'products_category_name_idx'],
        ['issued_items', ['branch_id', 'issue_date'], 'issued_branch_date_idx'],
        ['orders', ['branch_id', 'created_at'], 'orders_branch_created_idx'],
        ['customers', ['is_recurring', 'name'], 'customers_recurring_name_idx'],
        ['journal_entries', ['branch_id', 'date'], 'journal_branch_date_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            $this->addIndex($table, $columns, $name);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as [$table, $columns, $name]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $exists = collect(Schema::getIndexes($table))->contains(fn ($index) => $index['name'] === $name);

            if ($exists) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $indexes = collect(Schema::getIndexes($table));

        // Already covered by an index with the same columns (any name).
        if ($indexes->contains(fn ($index) => $index['columns'] === $columns)) {
            return;
        }

        if ($indexes->contains(fn ($index) => $index['name'] === $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }
};
