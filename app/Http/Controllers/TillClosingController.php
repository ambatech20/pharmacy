<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomerReturn;
use App\Models\Payment;
use App\Models\ShiftOpening;
use App\Models\TillClosing;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TillClosingController extends Controller
{
    public function __construct(private AccountingService $accountingService)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $branchId = $this->resolveBranchId($request, $user);

        $shift = $request->attributes->get('current_shift')
            ?? ShiftOpening::currentFor($user, $branchId);
        $periodStart = $this->periodStart($user->id, $branchId, $shift);

        // The float the shift started with. Without it the "expected" figure
        // ignores whatever was already sitting in the drawer, so a perfectly
        // ordinary day reports a shortage equal to the entire opening float.
        $openingCash = $shift ? (float) $shift->opening_cash : 0.0;
        $collected = $this->cashCollected($user->id, $branchId, $periodStart);
        $expectedCash = round($openingCash + $collected, 2);
        $otherPayments = $this->nonCashCollected($user->id, $branchId, $periodStart);

        $historyQuery = TillClosing::with(['user', 'branch'])->latest('closed_at');
        if ($user->branch_id) {
            $historyQuery->where('branch_id', $user->branch_id);
        }
        $closings = $historyQuery->paginate(10)->withQueryString();

        // Owner/Admin (no branch of their own) picks which drawer to count.
        $branches = $user->branch_id ? collect() : Branch::orderBy('name')->get();

        return view('till-closings.index', compact(
            'branchId',
            'branches',
            'shift',
            'openingCash',
            'collected',
            'periodStart',
            'expectedCash',
            'otherPayments',
            'closings'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $branchId = $this->resolveBranchId($request, $user);
        if (!$branchId) {
            return back()->withErrors(['branch_id' => 'Pick a branch for this till close.'])->withInput();
        }

        try {
            DB::transaction(function () use ($request, $user, $branchId) {
                $shift = ShiftOpening::currentFor($user, $branchId);

                // Recompute inside the transaction so two overlapping
                // closes can't both lock in the same expected figure.
                $periodStart = $this->periodStart($user->id, $branchId, $shift);
                $collected = $this->cashCollected($user->id, $branchId, $periodStart);
                $expectedCash = round(((float) ($shift->opening_cash ?? 0)) + $collected, 2);
                $counted = round((float) $request->counted_cash, 2);

                $closing = TillClosing::create([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'period_start' => $periodStart,
                    'closed_at' => now(),
                    'expected_cash' => $expectedCash,
                    'counted_cash' => $counted,
                    'variance' => round($counted - $expectedCash, 2),
                    'notes' => $request->notes,
                ]);

                // Put the cash difference in the books so the ledger cash
                // balance matches the physical drawer. No-op when it balances.
                $this->accountingService->recordTillVariance($closing->variance, $branchId, $closing->id);

                // Closing the till ends the shift. The next login then asks for
                // a fresh drawer count rather than reusing this one.
                if ($shift) {
                    $shift->update([
                        'closed_at' => now(),
                        'till_closing_id' => $closing->id,
                    ]);
                }
            });

            return redirect()->route('till-closings.index')->with('success', 'Shift closed. Your till was balanced against the drawer count you started with.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    private function resolveBranchId(Request $request, $user): ?int
    {
        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        $requested = $request->input('branch_id');
        return $requested ? (int) $requested : null;
    }

    /**
     * When this shift began. The drawer count is the real start of the shift.
     * Falling back to the previous close keeps the page working for a cashier
     * who somehow has no open shift.
     */
    private function periodStart(int $userId, ?int $branchId, ?ShiftOpening $shift = null): Carbon
    {
        if ($shift) {
            return $shift->opened_at->copy();
        }

        // Next close starts after the previous close for this cashier
        // at this branch; if they've never closed, start of today.
        $query = TillClosing::where('user_id', $userId);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $last = $query->latest('closed_at')->first();

        return $last?->closed_at?->copy() ?? Carbon::today()->startOfDay();
    }

    private function cashCollected(int $userId, ?int $branchId, Carbon $periodStart): float
    {
        if (!$branchId) {
            return 0.0;
        }

        $cashIn = (float) Payment::where('payment_method', 'cash')
            ->where('created_at', '>', $periodStart)
            ->whereHas('order', function ($q) use ($userId, $branchId) {
                $q->where('user_id', $userId)->where('branch_id', $branchId);
            })
            ->sum('amount');

        $cashRefunds = (float) CustomerReturn::where('refund_method', 'cash')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('created_at', '>', $periodStart)
            ->sum('refund_amount');

        return round($cashIn - $cashRefunds, 2);
    }

    private function nonCashCollected(int $userId, ?int $branchId, Carbon $periodStart): array
    {
        if (!$branchId) {
            return [];
        }

        return Payment::where('payment_method', '!=', 'cash')
            ->where('created_at', '>', $periodStart)
            ->whereHas('order', function ($q) use ($userId, $branchId) {
                $q->where('user_id', $userId)->where('branch_id', $branchId);
            })
            ->get()
            ->groupBy('payment_method')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();
    }
}
