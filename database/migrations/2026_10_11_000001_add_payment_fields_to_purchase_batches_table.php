<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PART A — how a purchase is paid.
 *
 * Non-destructive: every new column is nullable or has a safe default, and
 * existing rows are back-filled so old purchases stay valid:
 *  - payment_status defaults to 'paid' (old behaviour credited Payable but
 *    the system had no notion of unpaid stock; treating history as settled
 *    keeps "Owed to suppliers" clean until someone corrects a row).
 *  - amount_paid = line total for those historical rows.
 *  - purchase_ref = one unique ref per existing row so each legacy batch is
 *    its own payable group in Part B screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_batches')) {
            return;
        }

        Schema::table('purchase_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_batches', 'payment_status')) {
                $table->string('payment_status', 20)->default('paid')->after('purchase_date'); // paid | unpaid | partial
            }
            if (!Schema::hasColumn('purchase_batches', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_status');
            }
            if (!Schema::hasColumn('purchase_batches', 'due_date')) {
                $table->date('due_date')->nullable()->after('amount_paid');
            }
            if (!Schema::hasColumn('purchase_batches', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('purchase_batches', 'payment_reference')) {
                $table->string('payment_reference', 255)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('purchase_batches', 'check_number')) {
                $table->string('check_number', 100)->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('purchase_batches', 'check_date')) {
                $table->date('check_date')->nullable()->after('check_number');
            }
            if (!Schema::hasColumn('purchase_batches', 'check_bank')) {
                $table->string('check_bank', 255)->nullable()->after('check_date');
            }
            if (!Schema::hasColumn('purchase_batches', 'purchase_ref')) {
                $table->string('purchase_ref', 40)->nullable()->after('check_bank');
            }
            if (!Schema::hasColumn('purchase_batches', 'supplier_invoice_no')) {
                $table->string('supplier_invoice_no', 100)->nullable()->after('purchase_ref');
            }
        });

        // Index added outside the closure so re-runs on partially-migrated DBs don't fail.
        if (Schema::hasColumn('purchase_batches', 'purchase_ref') && !$this->hasIndex('purchase_batches', 'purchase_batches_purchase_ref_index')) {
            Schema::table('purchase_batches', function (Blueprint $table) {
                $table->index('purchase_ref');
            });
        }

        // Back-fill legacy rows: treat them as fully paid, give each its own ref.
        if (Schema::hasColumn('purchase_batches', 'purchase_ref')) {
            DB::table('purchase_batches')
                ->whereNull('purchase_ref')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $total = round((float) $row->quantity_received * (float) $row->cost_per_unit, 2);
                        DB::table('purchase_batches')->where('id', $row->id)->update([
                            'payment_status' => 'paid',
                            'amount_paid' => $total,
                            'purchase_ref' => 'LEGACY-' . str_pad((string) $row->id, 8, '0', STR_PAD_LEFT),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_batches')) {
            return;
        }

        Schema::table('purchase_batches', function (Blueprint $table) {
            foreach ([
                'supplier_invoice_no', 'purchase_ref', 'check_bank', 'check_date',
                'check_number', 'payment_reference', 'payment_method', 'due_date',
                'amount_paid', 'payment_status',
            ] as $column) {
                if (Schema::hasColumn('purchase_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list({$table})"))
                ->contains(fn ($i) => $i->name === $index);
        }

        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains(fn ($i) => $i->Key_name === $index);
    }
};
