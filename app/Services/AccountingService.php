<?php
namespace App\Services;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\Account;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class AccountingService {
    public function postEntry($desc, $branchId, $refType, $refId, $lines, $date = null) {
        return DB::transaction(function () use ($desc, $branchId, $refType, $refId, $lines, $date) {
            $debitTotal = collect($lines)->sum('debit');
            $creditTotal = collect($lines)->sum('credit');
            if (round($debitTotal, 2) !== round($creditTotal, 2)) {
                throw new \Exception("Debits and credits must balance.");
            }

            $entry = JournalEntry::create([
                'voucher_number' => 'JV-' . time() . '-' . rand(1000,9999),
                'date' => $date ?: now()->toDateString(),
                'description' => $desc,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'branch_id' => $branchId,
                'total_debit' => $debitTotal,
                'total_credit' => $creditTotal,
            ]);

            foreach ($lines as $line) {
                $acc = Account::where('code', $line['account_code'])->firstOrFail();
                Ledger::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $acc->id,
                    'branch_id' => $branchId,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['desc'] ?? $desc,
                ]);
            }
            return $entry;
        });
    }

    public function recordSale($amount, $cogs, $branchId, $refId, array $payments = []) {
        // Default keeps older callers working: a sale with no payment
        // rows is treated as a full cash collection.
        if (empty($payments)) {
            $payments = [['payment_method' => 'cash', 'amount' => $amount]];
        }

        $debitLines = [];
        foreach ($payments as $payment) {
            $method = $payment['payment_method'];
            $debitLines[] = [
                'account_code' => Payment::accountCodeFor($method),
                'debit' => $payment['amount'],
                'credit' => 0,
                'desc' => Payment::METHODS[$method] ?? $method,
            ];
        }
        $debitLines[] = ['account_code' => '4000', 'debit' => 0, 'credit' => $amount, 'desc' => 'Revenue'];

        $this->postEntry('Sales Revenue', $branchId, 'sale', $refId, $debitLines);
        $this->postEntry('COGS Entry', $branchId, 'sale', $refId, [
            ['account_code' => '5000', 'debit' => $cogs, 'credit' => 0, 'desc' => 'COGS'],
            ['account_code' => '1200', 'debit' => 0, 'credit' => $cogs, 'desc' => 'Inventory'],
        ]);
    }

    public function recordWriteOff($amount, $branchId, $refId, string $reasonLabel) {
        $this->postEntry('Stock write-off: ' . $reasonLabel, $branchId, 'adjustment', $refId, [
            ['account_code' => '5100', 'debit' => $amount, 'credit' => 0, 'desc' => 'Shrinkage'],
            ['account_code' => '1200', 'debit' => 0, 'credit' => $amount, 'desc' => 'Inventory'],
        ]);
    }

    public function recordCustomerReturn($refundAmount, $cogs, $branchId, $refId, string $refundMethod)
    {
        $refundAmount = round((float) $refundAmount, 2);
        $cogs = round((float) $cogs, 2);

        if ($refundAmount > 0) {
            $this->postEntry('Sales return', $branchId, 'customer_return', $refId, [
                ['account_code' => '4000', 'debit' => $refundAmount, 'credit' => 0, 'desc' => 'Revenue reversal'],
                ['account_code' => Payment::accountCodeFor($refundMethod), 'debit' => 0, 'credit' => $refundAmount, 'desc' => 'Refund'],
            ]);
        }

        if ($cogs > 0) {
            $this->postEntry('COGS reversal', $branchId, 'customer_return', $refId, [
                ['account_code' => '1200', 'debit' => $cogs, 'credit' => 0, 'desc' => 'Inventory'],
                ['account_code' => '5000', 'debit' => 0, 'credit' => $cogs, 'desc' => 'COGS reversal'],
            ]);
        }
    }

    public function recordSupplierReturn($amount, $branchId, $refId)
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return;
        }

        $this->postEntry('Purchase return', $branchId, 'supplier_return', $refId, [
            ['account_code' => '2000', 'debit' => $amount, 'credit' => 0, 'desc' => 'AP'],
            ['account_code' => '1200', 'debit' => 0, 'credit' => $amount, 'desc' => 'Inventory'],
        ]);
    }

    /**
     * Till close: reconcile the physical drawer with what the ledger already
     * holds. Sales/refunds posted to Cash (1001) as they happened, so only the
     * difference needs posting. An overage (counted above expected) debits Cash
     * and credits Cash Over/Short; a shortage does the reverse.
     */
    public function recordTillVariance($variance, $branchId, $refId)
    {
        $variance = round((float) $variance, 2);

        if ($variance === 0.0) {
            return;
        }

        $lines = $variance > 0
            ? [
                ['account_code' => '1001', 'debit' => $variance, 'credit' => 0, 'desc' => 'Cash over (till)'],
                ['account_code' => '5200', 'debit' => 0, 'credit' => $variance, 'desc' => 'Cash over/short'],
            ]
            : [
                ['account_code' => '5200', 'debit' => -$variance, 'credit' => 0, 'desc' => 'Cash over/short'],
                ['account_code' => '1001', 'debit' => 0, 'credit' => -$variance, 'desc' => 'Cash short (till)'],
            ];

        $this->postEntry('Till closing variance', $branchId, 'till_closing', $refId, $lines);
    }

    /**
     * Post the mirror image of an entry.
     *
     * Used when a document is deleted by mistake: the original stays in the
     * books and a reversal cancels it, so the trial balance still explains
     * itself. Expired, reported-on or audited periods keep their history.
     */
    public function reverseEntry(JournalEntry $entry, string $description): ?JournalEntry
    {
        $entry->loadMissing('ledgers.account');

        $lines = $entry->ledgers->map(fn (Ledger $ledger) => [
            'account_code' => $ledger->account->code,
            'debit' => (float) $ledger->credit,
            'credit' => (float) $ledger->debit,
            'desc' => 'Reversal: ' . $ledger->description,
        ])->all();

        if ($lines === []) {
            return null;
        }

        return $this->postEntry(
            $description,
            $entry->branch_id,
            $entry->reference_type . '_reversal',
            $entry->reference_id,
            $lines,
            $entry->date ? $entry->date->toDateString() : null
        );
    }
}