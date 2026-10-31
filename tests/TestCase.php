<?php

namespace Tests;

use App\Models\Branch;
use App\Models\ShiftOpening;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Blade views use @vite; there is no compiled bundle in CI, so stub it.
        $this->withoutVite();
    }

    /**
     * Sign in AND open a shift, because the app refuses to serve any screen
     * until the drawer has been counted. Tests that are about something else
     * should not each have to remember that.
     *
     * Tests covering the gate itself use actingAsWithoutShift().
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($user instanceof User) {
            $this->openShiftFor($user);
        }

        return parent::actingAs($user, $guard);
    }

    /** Sign in without counting the drawer - i.e. mid-shift, or testing the gate. */
    protected function actingAsWithoutShift(User $user, $guard = null)
    {
        return parent::actingAs($user, $guard);
    }

    /**
     * A shift with an empty drawer. Only the existence of the row matters to
     * the middleware; the amount is irrelevant to everything else.
     */
    protected function openShiftFor(User $user, float $amount = 0.0): ShiftOpening
    {
        $branchId = $user->branch_id ?: $this->someBranchId();

        return ShiftOpening::create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'opened_at' => now(),
            'opening_cash' => $amount,
        ]);
    }

    /**
     * Every screen in a single-pharmacy install belongs to one branch. Most
     * tests create their own before signing in; the few that do not still need
     * one to open a shift against.
     */
    private function someBranchId(): int
    {
        return Branch::query()->value('id') ?: Branch::create(['name' => 'Main Branch'])->id;
    }
}
