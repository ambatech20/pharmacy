<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PART A + PART B — paying for purchases, owing suppliers, reminders.
 */
class SupplierOwedTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $clerk;
    protected Branch $branch;
    protected Branch $otherBranch;
    protected Supplier $supplier;
    protected Drug $drug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->otherBranch = Branch::create(['name' => 'Other Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply', 'default_credit_days' => 45]);
        $this->drug = Drug::create(['name' => 'Paracetamol', 'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id, 'unit_top' => 'pack', 'top_holds' => 10, 'unit_base' => 'tablet']);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password',
            'role' => User::ROLE_OWNER_ADMIN, 'branch_id' => null,
        ]);
        $this->clerk = User::create([
            'name' => 'Clerk', 'email' => 'clerk@example.com', 'password' => 'password',
            'role' => User::ROLE_PHARMACIST_CLERK, 'branch_id' => $this->branch->id,
        ]);
    }

    protected function postPurchase(array $overrides = [])
    {
        return $this->actingAs($this->owner)->post(route('purchases.store'), array_merge([
            // Unique ref per submission so separate purchases never merge
            // into one invoice group (mimics the controller's behaviour).
            'purchase_ref' => 'REF-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug', 'item_id' => $this->drug->id, 'batch_number' => 'B-1',
                'quantity_received' => 10, 'quantity_unit' => 'tablet',
                'cost_per_unit' => 20, 'selling_price_per_unit' => 30,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ], $overrides));
    }

    public function test_paid_now_purchase_posts_to_the_method_account_and_balances(): void
    {
        $this->postPurchase(['payment_method' => 'bank_transfer', 'payment_reference' => 'TRX-9'])->assertRedirect(route('purchases.index'));

        $batch = PurchaseBatch::first();
        $this->assertSame('paid', $batch->payment_status);
        $this->assertEquals(200.0, (float) $batch->amount_paid);
        $this->assertSame('TRX-9', $batch->payment_reference);

        // Behind the scenes: Inventory up (debit), bank account down (credit). Balanced.
        $entry = \App\Models\JournalEntry::where('reference_type', 'purchase')->first();
        $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);
        $this->assertDatabaseHas('ledgers', ['journal_entry_id' => $entry->id, 'debit' => 200, 'credit' => 0]);
        $bankCredit = Ledger::where('journal_entry_id', $entry->id)->where('credit', 200)->first();
        $this->assertSame('1012', Account::find($bankCredit->account_id)->code); // bank_transfer's existing account
        // Nothing went to the owed-to-suppliers account.
        $owed = Account::where('code', Payment::OWED_TO_SUPPLIERS_ACCOUNT)->first();
        $this->assertSame(0.0, (float) Ledger::where('account_id', $owed->id)->sum('credit'));
    }

    public function test_pay_later_purchase_creates_one_owed_group_with_due_date(): void
    {
        $this->postPurchase([
            'payment_method' => Payment::PAY_LATER,
            'due_date' => now()->addDays(30)->toDateString(),
            'supplier_invoice_no' => 'INV-77',
            'items' => [
                ['item_type' => 'drug', 'item_id' => $this->drug->id, 'batch_number' => 'B-1', 'quantity_received' => 10, 'quantity_unit' => 'tablet', 'cost_per_unit' => 20, 'selling_price_per_unit' => 30, 'expiry_date' => now()->addYear()->toDateString()],
                ['item_type' => 'drug', 'item_id' => $this->drug->id, 'batch_number' => 'B-2', 'quantity_received' => 5, 'quantity_unit' => 'tablet', 'cost_per_unit' => 20, 'selling_price_per_unit' => 30, 'expiry_date' => now()->addYear()->toDateString()],
            ],
        ])->assertRedirect(route('purchases.index'));

        $batches = PurchaseBatch::get();
        $this->assertCount(2, $batches);
        $this->assertTrue($batches->pluck('purchase_ref')->unique()->count() === 1); // one invoice group
        foreach ($batches as $b) {
            $this->assertSame('unpaid', $b->payment_status);
            $this->assertEquals(0.0, (float) $b->amount_paid);
            $this->assertNotNull($b->due_date);
        }

        // Single liability: owed-to-suppliers account increased by 300, balanced.
        $owed = Account::where('code', Payment::OWED_TO_SUPPLIERS_ACCOUNT)->first();
        $this->assertEqualsWithDelta(300.0, (float) Ledger::where('account_id', $owed->id)->sum('credit') - (float) Ledger::where('account_id', $owed->id)->sum('debit'), 0.001);
        foreach (\App\Models\JournalEntry::where('reference_type', 'purchase')->get() as $entry) {
            $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);
        }
    }

    public function test_partial_supplier_payment_updates_balance_and_posts_reduction(): void
    {
        $this->postPurchase([
            'payment_method' => Payment::PAY_LATER,
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['item_type' => 'drug', 'item_id' => $this->drug->id, 'batch_number' => 'B-1', 'quantity_received' => 10, 'quantity_unit' => 'tablet', 'cost_per_unit' => 20, 'selling_price_per_unit' => 30, 'expiry_date' => now()->addYear()->toDateString()],
                ['item_type' => 'drug', 'item_id' => $this->drug->id, 'batch_number' => 'B-2', 'quantity_received' => 5, 'quantity_unit' => 'tablet', 'cost_per_unit' => 20, 'selling_price_per_unit' => 30, 'expiry_date' => now()->addYear()->toDateString()],
            ],
        ]);
        $ref = PurchaseBatch::first()->purchase_ref;

        // Pay 250 of 300, ticking the invoice, by mobile money.
        $this->actingAs($this->owner)->post(route('supplier-payments.store', $this->supplier), [
            'payment_date' => now()->toDateString(),
            'amount' => 250,
            'payment_method' => 'telebirr',
            'payment_reference' => 'TB-123',
            'invoices' => [$ref => 300],
        ])->assertRedirect(route('supplier-owed.show', $this->supplier));

        $batches = PurchaseBatch::orderBy('id')->get();
        $this->assertEqualsWithDelta(200.0, (float) $batches[0]->amount_paid, 0.001);
        $this->assertSame('paid', $batches[0]->payment_status);
        $this->assertEqualsWithDelta(50.0, (float) $batches[1]->amount_paid, 0.001); // oldest-first spills over
        $this->assertSame('partial', $batches[1]->payment_status);

        // Posting: owed account reduced 250, telebirr account reduced 250, entry balances.
        $owed = Account::where('code', Payment::OWED_TO_SUPPLIERS_ACCOUNT)->first();
        $tb = Account::where('code', Payment::accountCodeFor('telebirr'))->first();
        $this->assertEqualsWithDelta(250.0, (float) Ledger::where('account_id', $tb->id)->sum('credit'), 0.001);
        // Owed account: credited 300 by the purchase, debited 250 by this payment -> net 50 still owed.
        $this->assertEqualsWithDelta(50.0, (float) Ledger::where('account_id', $owed->id)->sum('credit') - (float) Ledger::where('account_id', $owed->id)->sum('debit'), 0.001);
        $entry = \App\Models\JournalEntry::where('reference_type', 'supplier_payment')->first();
        $this->assertNotNull($entry);
        $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);
        $this->assertEqualsWithDelta(250.0, (float) $entry->total_debit, 0.001);

        // Remaining owed is calculated from purchases: 50.
        $summary = app(\App\Services\SupplierOwedService::class)->supplierSummary(null, 7);
        $this->assertEqualsWithDelta(50.0, $summary[0]['total_owed'], 0.001);
    }

    public function test_overpaying_a_supplier_is_rejected(): void
    {
        $this->postPurchase(['payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(30)->toDateString()]);

        $response = $this->actingAs($this->owner)->from(route('supplier-payments.create', $this->supplier))
            ->post(route('supplier-payments.store', $this->supplier), [
                'payment_date' => now()->toDateString(),
                'amount' => 250, // only 200 owed
                'payment_method' => 'cash',
            ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertEquals(0.0, (float) PurchaseBatch::first()->amount_paid);
    }

    public function test_reminders_separate_overdue_from_due_within_window(): void
    {
        // Use a supplier with no credit-period default so the form's due date is honoured.
        $s1 = Supplier::create(['name' => 'Late Supply']);
        $this->postPurchase(['supplier_id' => $s1->id, 'payment_method' => Payment::PAY_LATER, 'due_date' => now()->subDay()->toDateString()]);   // overdue
        $s2 = Supplier::create(['name' => 'Soon Supply']);
        $this->postPurchase(['supplier_id' => $s2->id, 'payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(5)->toDateString()]); // soon
        $s3 = Supplier::create(['name' => 'Later Supply']);
        $this->postPurchase(['supplier_id' => $s3->id, 'payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(60)->toDateString()]); // ok

        $data = app(\App\Services\SupplierOwedService::class)->reminderData(null, 7);
        $this->assertCount(1, $data['overdue']);
        $this->assertCount(1, $data['due_soon']);
        $this->assertSame(2, $data['badge_count']);
        $this->assertEqualsWithDelta(200.0, $data['overdue'][0]['balance'], 0.001);

        // Alerts page shows them to the owner...
        $this->actingAs($this->owner)->get(route('alerts.index'))
            ->assertOk()->assertSee('Overdue')->assertSee('Soon Supply');
        // ...and the dashboard too, now inside the single alerts card as its
        // own segment rather than a card of its own.
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Things that need you')
            ->assertSee('1 payment overdue');
    }

    public function test_clerk_cannot_access_owed_pages_or_record_payments(): void
    {
        $this->postPurchase(['payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($this->clerk)->get(route('supplier-owed.index'))->assertForbidden();
        $this->actingAs($this->clerk)->get(route('supplier-owed.show', $this->supplier))->assertForbidden();
        $this->actingAs($this->clerk)->get(route('supplier-payments.create', $this->supplier))->assertForbidden();
        $this->actingAs($this->clerk)->post(route('supplier-payments.store', $this->supplier), [
            'payment_date' => now()->toDateString(), 'amount' => 10, 'payment_method' => 'cash',
        ])->assertForbidden();
        $this->assertDatabaseCount('supplier_payments', 0);
    }

    public function test_branch_isolation_for_owed_suppliers(): void
    {
        // Same supplier, two branches: each branch owes its own purchase.
        $this->postPurchase(['payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(30)->toDateString()]);
        $this->postPurchase(['branch_id' => $this->otherBranch->id, 'batch_number' => 'OB-1', 'payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(30)->toDateString()]);

        $manager = User::create(['name' => 'BM', 'email' => 'bm@example.com', 'password' => 'password', 'role' => User::ROLE_BRANCH_MANAGER, 'branch_id' => $this->branch->id]);
        $mainOnly = app(\App\Services\SupplierOwedService::class)->supplierSummary($this->branch->id, 7);
        $this->assertCount(1, $mainOnly);
        $this->assertEqualsWithDelta(200.0, $mainOnly[0]['total_owed'], 0.001);

        $all = app(\App\Services\SupplierOwedService::class)->supplierSummary(null, 7);
        $this->assertEqualsWithDelta(400.0, $all[0]['total_owed'], 0.001);
    }

    public function test_alert_window_is_a_setting(): void
    {
        Setting::put('supplier_alert_days', '14');
        // Supplier with no credit-period default so the 10-day due date is honoured.
        $s = Supplier::create(['name' => 'Window Supply']);
        $this->postPurchase(['supplier_id' => $s->id, 'payment_method' => Payment::PAY_LATER, 'due_date' => now()->addDays(10)->toDateString()]);

        $data = app(\App\Services\SupplierOwedService::class)->reminderData(null, (int) Setting::value('supplier_alert_days', '7'));
        $this->assertCount(1, $data['due_soon']);
        $this->assertSame(14, $data['soon_days']);
    }

    public function test_check_purchase_stores_check_details_and_pays_from_the_bank_account(): void
    {
        $due = now()->addDays(20)->toDateString();

        $this->postPurchase([
            'payment_method' => 'check',
            'check_number' => 'CHQ-0042',
            'check_date' => $due,
            'check_bank' => 'Commercial Bank of Ethiopia',
        ])->assertRedirect(route('purchases.index'));

        $batch = PurchaseBatch::firstOrFail();
        $this->assertSame('paid', $batch->payment_status);
        $this->assertSame('CHQ-0042', $batch->check_number);
        $this->assertSame($due, $batch->check_date->toDateString());
        $this->assertSame('Commercial Bank of Ethiopia', $batch->check_bank);
        // The check number is kept as the reference, so the clerk sees one number.
        $this->assertSame('CHQ-0042', $batch->payment_reference);

        // Money leaves the bank book (checks share it), nothing is owed.
        $entry = \App\Models\JournalEntry::where('reference_type', 'purchase')->firstOrFail();
        $credit = Ledger::where('journal_entry_id', $entry->id)->where('credit', '>', 0)->firstOrFail();
        $this->assertSame('1012', Account::find($credit->account_id)->code);
        $this->assertSame('check', $batch->payment_method);
    }

    public function test_a_check_must_carry_its_number_date_and_bank(): void
    {
        $this->postPurchase(['payment_method' => 'check'])
            ->assertSessionHasErrors(['check_number', 'check_date', 'check_bank']);
    }

    public function test_non_cash_paid_now_methods_need_a_reference(): void
    {
        $this->postPurchase(['payment_method' => 'telebirr'])->assertSessionHasErrors('payment_reference');
        // Cash moves no traceable record, so it stays optional.
        $this->postPurchase(['payment_method' => 'cash'])->assertSessionHasNoErrors();
    }

    public function test_pay_later_requires_a_supplier_and_a_due_date(): void
    {
        $this->postPurchase(['payment_method' => Payment::PAY_LATER])
            ->assertSessionHasErrors('due_date');

        $this->postPurchase([
            'payment_method' => Payment::PAY_LATER,
            'due_date' => now()->addDays(30)->toDateString(),
            'supplier_id' => '',
        ])->assertSessionHasErrors('supplier_id');
    }

    public function test_pay_later_keeps_the_supplier_and_stays_unpaid_until_paid(): void
    {
        // The 30/60-day quick choice is client-side; what matters on the row is
        // that the supplier and the agreed due date are stored, nothing paid.
        $this->postPurchase([
            'payment_method' => Payment::PAY_LATER,
            'due_date' => now()->addDays(45)->toDateString(),
        ])->assertSessionHasNoErrors();

        $batch = PurchaseBatch::firstOrFail();
        $this->assertSame('unpaid', $batch->payment_status);
        $this->assertEquals(0.0, (float) $batch->amount_paid);
        $this->assertSame(now()->addDays(45)->toDateString(), $batch->due_date->toDateString());
        $this->assertSame($this->supplier->id, $batch->supplier_id);
    }
}
