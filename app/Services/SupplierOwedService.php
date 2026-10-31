<?php

namespace App\Services;

use App\Models\PurchaseBatch;
use Illuminate\Support\Facades\DB;

/**
 * PART B — everything about "Owed to suppliers" lives here so the page,
 * the Pay supplier screen, the Alerts page, the dashboard and the sidebar
 * badge all agree on the same numbers. Balances are always CALCULATED from
 * purchase batches (never stored separately), grouped by purchase_ref so a
 * multi-line purchase behaves as one invoice.
 */
class SupplierOwedService
{
    /**
     * Branch scope helper: users with a branch see only their branch's
     * debts; owner/admin (branch_id null) sees everything. Same pattern
     * as the rest of the app.
     */
    protected function applyScope($query, ?int $branchId): void
    {
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }
    }

    /**
     * One row per supplier that is still owed money:
     * total owed, nearest due date, status (overdue | soon | ok).
     */
    public function supplierSummary(?int $branchId, int $soonDays = 7): array
    {
        // Outstanding lines: pay-later purchases not fully settled.
        return $this->summariseLines($this->openLinesQuery($branchId)->get(), $soonDays);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurchaseBatch>  $lines
     */
    protected function summariseLines($lines, int $soonDays): array
    {
        return $lines->groupBy('supplier_id')->map(function ($supplierLines, $supplierId) use ($soonDays) {
            $owed = $supplierLines->sum(fn ($l) => max(0, round((float) $l->total_cost - (float) $l->amount_paid, 2)));
            $nearestDue = $supplierLines->map->due_date->filter()->sort()->first();

            return [
                'supplier_id' => (int) $supplierId,
                'supplier_name' => $supplierLines->first()->supplier?->name ?? '—',
                'total_owed' => round($owed, 2),
                'nearest_due' => $nearestDue,
                'status' => $this->statusForDate($nearestDue, $soonDays),
                'invoice_count' => $supplierLines->pluck('purchase_ref')->filter()->unique()->count(),
            ];
        })->sortByDesc('total_owed')->values()->all();
    }

    /**
     * The open invoices (purchase_ref groups) for one supplier, each with
     * its lines underneath. Amounts come from the batch rows.
     */
    public function supplierInvoices(int $supplierId, ?int $branchId): array
    {
        $lines = $this->openLinesQuery($branchId)->where('supplier_id', $supplierId)->get();

        return $lines->groupBy(fn ($l) => $l->purchase_ref ?? ('BATCH-' . $l->id))
            ->map(function ($group, $ref) {
                $total = $group->sum(fn ($l) => round((float) $l->total_cost, 2));
                $paid = $group->sum(fn ($l) => round((float) $l->amount_paid, 2));

                return [
                    'purchase_ref' => $ref,
                    'supplier_invoice_no' => $group->first()->supplier_invoice_no,
                    'date' => $group->pluck('purchase_date')->filter()->sort()->first(),
                    'due_date' => $group->map->due_date->filter()->sort()->first(),
                    'total' => round($total, 2),
                    'paid' => round($paid, 2),
                    'balance' => round(max(0, $total - $paid), 2),
                    'status' => $paid <= 0 ? 'unpaid' : ($paid < $total ? 'partial' : 'paid'),
                    'lines' => $group->values()->all(),
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * Reminders for the Alerts page / dashboard / sidebar badge. Calculated
     * live from the database — no cron needed. The heavy queries run at most
     * once per request (memoised) so the view composer, the Alerts page and
     * the dashboard never double-query.
     */
    private array $memo = []; // [branchId, soonDays] => data

    public function reminderData(?int $branchId, int $soonDays = 7): array
    {
        foreach ($this->memo as $entry) {
            if (count($entry) === 3 && $entry[0] === $branchId && $entry[1] === $soonDays) {
                return $entry[2];
            }
        }
        $data = $this->computeReminderData($branchId, $soonDays);
        $this->memo[] = [$branchId, $soonDays, $data];

        return $data;
    }

    protected function computeReminderData(?int $branchId, int $soonDays): array
    {
        // Both the supplier summary and the invoice list need the same open
        // purchase lines. Read them once instead of running the query twice.
        $lines = $this->openLinesQuery($branchId)->get();

        $groups = collect($this->summariseLines($lines, $soonDays));

        $overdue = $groups->filter(fn ($s) => $s['status'] === 'overdue')->values()->all();
        $dueSoon = $groups->filter(fn ($s) => $s['status'] === 'soon')->values()->all();

        // Per-invoice detail for the Alerts page listing. Group by an
        // explicit string key (collection group keys are cast to strings,
        // so a raw array key would break when a value inside is null).
        $invoices = collect();
        foreach ($lines->groupBy(fn ($l) => ((int) $l->supplier_id).'|'.($l->purchase_ref ?? ('BATCH-'.$l->id))) as $key => $group) {
            [$supplierId, $ref] = explode('|', (string) $key, 2);
            $balance = $group->sum(fn ($l) => max(0, round((float) $l->total_cost - (float) $l->amount_paid, 2)));
            if ($balance <= 0) {
                continue;
            }
            $due = $group->map->due_date->filter()->sort()->first();
            if (!$due) {
                continue;
            }
            $due = \Carbon\Carbon::parse($due);
            $invoices->push([
                'supplier_id' => (int) $supplierId,
                'supplier_name' => $group->first()->supplier?->name ?? '—',
                'purchase_ref' => $ref ?? '—',
                'supplier_invoice_no' => $group->first()->supplier_invoice_no,
                'balance' => round($balance, 2),
                'due_date' => $due,
                'status' => $this->statusForDate($due, $soonDays),
            ]);
        }

        $overdueInvoices = $invoices->filter(fn ($i) => $i['status'] === 'overdue')->sortBy('due_date')->values()->all();
        $soonInvoices = $invoices->filter(fn ($i) => $i['status'] === 'soon')->sortBy('due_date')->values()->all();

        return [
            'soon_days' => $soonDays,
            'suppliers' => $groups->all(),
            'overdue' => $overdueInvoices,
            'due_soon' => $soonInvoices,
            'total_owed' => round($invoices->sum('balance'), 2),
            'badge_count' => count($overdueInvoices) + count($soonInvoices),
        ];
    }

    /**
     * Status for a due date: overdue (past), soon (within window), ok.
     */
    public function statusForDate($dueDate, int $soonDays = 7): string
    {
        if (!$dueDate) {
            return 'ok';
        }
        $due = \Carbon\Carbon::parse($dueDate)->startOfDay();
        if ($due->isBefore(now()->startOfDay())) {
            return 'overdue';
        }
        if ($due->lte(now()->startOfDay()->addDays($soonDays))) {
            return 'soon';
        }

        return 'ok';
    }

    /**
     * The core query: pay-later purchase lines that aren't fully settled.
     */
    protected function openLinesQuery(?int $branchId)
    {
        $query = PurchaseBatch::with(['supplier'])
            ->where('payment_method', \App\Models\Payment::PAY_LATER)
            ->whereIn('payment_status', ['unpaid', 'partial']);

        $this->applyScope($query, $branchId);

        return $query->orderBy('due_date');
    }
}
