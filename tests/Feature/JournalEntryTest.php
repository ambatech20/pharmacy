<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AccountSeeder::class);
    }

    protected function balancedLines(): array
    {
        return [
            ['account_id' => Account::where('code', '1001')->value('id'), 'debit' => 10, 'credit' => 0],
            ['account_id' => Account::where('code', '4000')->value('id'), 'debit' => 0, 'credit' => 10],
        ];
    }

    protected function postEntry(string $desc, int $branchId): void
    {
        (new AccountingService())->postEntry($desc, $branchId, 'manual', null, [
            ['account_code' => '1001', 'debit' => 10, 'credit' => 0, 'desc' => $desc],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 10, 'desc' => $desc],
        ]);
    }

    /**
     * Regression: ->where('branch_id', null) compiles to "branch_id IS NULL"
     * and hid every entry from an Owner/Admin with no fixed branch.
     */
    public function test_owner_with_no_branch_sees_entries_from_every_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $this->postEntry('Entry for Branch A', $branchA->id);
        $this->postEntry('Entry for Branch B', $branchB->id);

        $html = $this->actingAs($owner)->get(route('journal-entries.index'))->content();
        $this->assertStringContainsString('Entry for Branch A', $html);
        $this->assertStringContainsString('Entry for Branch B', $html);
    }

    public function test_branch_scoped_user_only_sees_their_own_entries(): void
    {
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $managerA = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id]);

        $this->postEntry('Entry for Branch A', $branchA->id);
        $this->postEntry('Entry for Branch B', $branchB->id);

        $html = $this->actingAs($managerA)->get(route('journal-entries.index'))->content();
        $this->assertStringContainsString('Entry for Branch A', $html);
        $this->assertStringNotContainsString('Entry for Branch B', $html);
    }

    /**
     * Single-branch install: journal_entries.branch_id is NOT NULL and the
     * form no longer asks which branch, so an Owner/Admin with no branch is
     * stamped with the pharmacy's one branch instead of being blocked.
     */
    public function test_owner_with_no_branch_posts_to_the_single_branch_automatically(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $this->actingAs($owner)->post(route('journal-entries.store'), [
            'description' => 'Manual entry',
            'lines' => $this->balancedLines(),
        ])->assertRedirect(route('journal-entries.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_entries', [
            'branch_id' => $branch->id,
            'description' => 'Manual entry',
        ]);
    }

    public function test_owner_with_no_branch_posts_to_the_branch_they_choose(): void
    {
        $branch = Branch::create(['name' => 'Branch A']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $this->actingAs($owner)->post(route('journal-entries.store'), [
            'description' => 'Manual entry',
            'branch_id' => $branch->id,
            'lines' => $this->balancedLines(),
        ])->assertRedirect(route('journal-entries.index'));

        $this->assertDatabaseHas('journal_entries', ['branch_id' => $branch->id, 'description' => 'Manual entry']);
    }

    public function test_branch_manager_posts_to_their_own_branch_without_choosing(): void
    {
        $branch = Branch::create(['name' => 'Branch A']);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);

        $this->actingAs($manager)->post(route('journal-entries.store'), [
            'description' => 'Manual entry',
            'lines' => $this->balancedLines(),
        ])->assertRedirect(route('journal-entries.index'));

        $this->assertDatabaseHas('journal_entries', ['branch_id' => $branch->id, 'description' => 'Manual entry']);
    }
}
