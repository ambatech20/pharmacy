<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that run on every page or on the till screen.
 *
 * The first index pass covered stock, names and report dates. These cover the
 * shift/till lookups and the payment/return sums the end-of-shift screen makes
 * several times in one request.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string, 1:array<int,string>, 2:string}> */
    private array $indexes = [
        ['purchase_batches', ['branch_id', 'payment_method', 'payment_status', 'due_date'], 'pb_branch_payment_due_idx'],
        ['payments', ['payment_method', 'created_at'], 'payments_method_created_idx'],
        ['payments', ['created_at'], 'payments_created_idx'],
        ['customer_returns', ['branch_id', 'user_id', 'refund_method', 'created_at'], 'returns_branch_user_method_idx'],
        ['till_closings', ['branch_id', 'closed_at'], 'till_branch_closed_idx'],
        ['till_closings', ['user_id', 'branch_id', 'closed_at'], 'till_user_branch_closed_idx'],
        ['ledgers', ['branch_id', 'account_id'], 'ledgers_branch_account_idx'],
        ['shift_openings', ['user_id', 'branch_id', 'closed_at', 'opened_at'], 'shift_user_branch_open_idx'],
        ['expenses', ['branch_id', 'repeat_monthly', 'next_due_date'], 'expenses_branch_repeat_due_idx'],
        ['customers', ['is_recurring', 'reminder_interval_days'], 'customers_recurring_interval_idx'],
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

        if ($indexes->contains(fn ($index) => $index['columns'] === $columns)) {
            return;
        }

        if ($indexes->contains(fn ($index) => $index['name'] === $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }
};
