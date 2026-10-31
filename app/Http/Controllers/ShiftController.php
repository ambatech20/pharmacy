<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ShiftOpening;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The drawer count that starts a shift.
 *
 * This replaces the old Opening Balances screen. That one asked for eight
 * figures on a dedicated page; the ones a cashier can actually know at 8am
 * are "what is in the drawer", so that is the only question left.
 *
 * The count is a baseline, not a transaction. It is not posted to the ledger:
 * the float is already in the cash account from wherever it came from, and
 * booking it again every morning would double-count it forever. What it does
 * is give TillClosingController a starting point, so the end-of-shift
 * variance measures today's mistakes instead of the opening float.
 */
class ShiftController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $branchId = $this->branchId($user);

        $shift = ShiftOpening::currentFor($user, $branchId);

        return view('shifts.open', [
            'branchId' => $branchId,
            'branch' => $branchId ? Branch::find($branchId) : null,
            // Already counted? Show it rather than ask again.
            'current' => $shift,
            'intendedUrl' => $this->intendedUrl($request),
            'lastClosed' => ShiftOpening::query()
                ->where('user_id', $user->id)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereNotNull('closed_at')
                ->latest('closed_at')
                ->first(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            // A number is required - no skipping. Zero is a real answer for an
            // empty drawer; an empty box is not.
            'opening_cash' => 'required|numeric|min:0|max:999999999',
            'intended_url' => 'nullable|string|max:2048',
        ]);

        $branchId = $this->branchId($user);

        if (! $branchId) {
            return back()->withErrors([
                'opening_cash' => 'No pharmacy is set up yet, so there is no drawer to count. Run the installer first.',
            ])->withInput();
        }

        // Two tabs, or a double submit: keep the first count, ignore the rest.
        if ($shift = ShiftOpening::currentFor($user, $branchId)) {
            return redirect()->to($this->intendedUrl($request))
                ->with('success', 'Your shift was already open with ETB '
                    . number_format((float) $shift->opening_cash, 2) . ' in the drawer.');
        }

        ShiftOpening::create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'opened_at' => now(),
            'opening_cash' => round((float) $validated['opening_cash'], 2),
        ]);

        return redirect()->to($this->intendedUrl($request))
            ->with('success', 'Shift started. Have a good day.');
    }

    private function branchId(User $user): ?int
    {
        return $user->branch_id ?: Branch::query()->orderBy('id')->value('id');
    }

    /**
     * Where to send them once the drawer is counted.
     *
     * Only ever a path on this site. The value arrives flashed from the
     * middleware, but it is still user-reachable input, so it is checked
     * rather than trusted: one leading slash, no scheme, no host, no
     * protocol-relative "//evil.example".
     */
    private function intendedUrl(Request $request): string
    {
        $candidate = (string) ($request->input('intended_url') ?: session('intended_url'));
        $dashboard = route('dashboard');

        if (
            $candidate === ''
            || ! str_starts_with($candidate, '/')
            || str_starts_with($candidate, '//')
            || str_contains($candidate, '\\')
            || preg_match('/[\r\n]/', $candidate)
        ) {
            return $dashboard;
        }

        return $candidate;
    }
}
