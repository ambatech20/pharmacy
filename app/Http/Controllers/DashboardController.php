<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Support\BackupHealth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Everything the shortcut picker can offer.
     *
     * Keyed by a stable slug rather than a route name so a rename cannot
     * silently orphan someone's saved shortcuts. Each entry carries its own
     * tint, which is why these are arrays and not just route names.
     *
     * `roles` limits an entry to the roles that can actually open it, so a
     * clerk is never offered "Expenses" and then given a 403.
     */
    public const AVAILABLE_SHORTCUTS = [
        'sale' => [
            'label' => 'Start a sale',
            'route' => 'dispense.create',
            'classes' => 'bg-[#e7f7f4] text-[#08796f] hover:bg-[#d7f1ed]',
        ],
        'purchase' => [
            'label' => 'Record purchase',
            'route' => 'purchases.create',
            'classes' => 'bg-[#fff2dc] text-[#8a5700] hover:bg-[#ffebc8]',
        ],
        'adjust' => [
            'label' => 'Adjust stock',
            'route' => 'adjustments.create',
            'classes' => 'bg-[#eef1f5] text-[#405166] hover:bg-[#e5eaf0]',
        ],
        'return' => [
            'label' => 'Record return',
            'route' => 'returns.index',
            'classes' => 'bg-[#fdeef2] text-[#9f1239] hover:bg-[#fbe0e7]',
        ],
        'customer' => [
            'label' => 'Add customer',
            'route' => 'customers.create',
            'classes' => 'bg-[#eef2ff] text-[#3730a3] hover:bg-[#e2e8ff]',
        ],
        'drug' => [
            'label' => 'Add drug',
            'route' => 'drugs.create',
            'classes' => 'bg-[#e9f7f1] text-[#065f46] hover:bg-[#d9f2e7]',
        ],
        'supplier' => [
            'label' => 'Add supplier',
            'route' => 'suppliers.create',
            'classes' => 'bg-[#f5f0e6] text-[#6b5310] hover:bg-[#efe7d5]',
        ],
        'expense' => [
            'label' => 'Record expense',
            'route' => 'expenses.create',
            'classes' => 'bg-[#fdf2f8] text-[#9d174d] hover:bg-[#fbe7f3]',
            'roles' => ['owner_admin'],
        ],
        'till' => [
            'label' => 'End shift',
            'route' => 'till-closings.index',
            'classes' => 'bg-[#fef3c7] text-[#92400e] hover:bg-[#fde9b8]',
        ],
        'reports' => [
            'label' => 'Sales report',
            'route' => 'reports.sales',
            'classes' => 'bg-[#eef1f5] text-[#405166] hover:bg-[#e5eaf0]',
            'roles' => ['owner_admin', 'branch_manager'],
        ],
    ];

    /** What someone gets before they have ever opened the picker. */
    public const DEFAULT_SHORTCUTS = ['sale', 'purchase', 'adjust'];

    public function index(Request $request)
    {
        $user = Auth::user();
        $branchId = $user->branch_id;

        // Same window as the Alerts page, so the two never disagree.
        $expiryDays = Setting::expiryAlertDays();

        $cacheKey = 'dashboard:' . ($branchId ?? 'all') . ':expiry:' . $expiryDays;

        $data = Cache::remember($cacheKey, 60, function () use ($user, $branchId, $expiryDays) {
            $scope = fn ($query, $column = 'branch_id') => $branchId ? $query->where($column, $branchId) : $query;

            $totalDrugs = $scope(DB::table('purchase_batches'))
                ->whereNotNull('drug_id')
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
                ->distinct('drug_id')
                ->count('drug_id');

            // Low stock is a property of the drug, not of one batch: two
            // batches of 60 are 120 on the shelf even though each is under a
            // 100-tablet minimum. This asks the same question as the Alerts
            // page (sum, then compare) so the count on this card always equals
            // the list it links to.
            $lowStockCount = DB::query()->fromSub(
                DB::table('drugs as d')
                    ->leftJoin('purchase_batches as pb', function ($join) use ($branchId) {
                        $join->on('d.id', '=', 'pb.drug_id');
                        // Expired stock cannot be sold, so it must not count
                        // towards the minimum. This keeps the card equal to
                        // the Alerts list, which applies the same rule.
                        $join->where(fn ($q) => $q
                            ->whereNull('pb.expiry_date')
                            ->orWhere('pb.expiry_date', '>=', Carbon::today()->toDateString()));
                        if ($branchId) {
                            $join->where('pb.branch_id', $branchId);
                        }
                    })
                    ->select('d.id')
                    ->groupBy('d.id', 'd.minimum_stock_level', 'd.units_per_top')
                    ->havingRaw('COALESCE(SUM(pb.quantity_remaining), 0) <= d.minimum_stock_level * d.units_per_top'),
                'low_stock_drugs'
            )->count();

            // Counted directly rather than by loading the rows: the dashboard
            // only ever showed the number, and a "limit 5" fetch followed by
            // ->count() reported the page size, not the total.
            $expiringSoonCount = $scope(
                DB::table('purchase_batches as pb')
                    ->join('drugs as d', 'pb.drug_id', '=', 'd.id')
                    ->whereNotNull('pb.drug_id')
                    ->whereBetween('pb.expiry_date', [Carbon::today(), Carbon::today()->addDays($expiryDays)])
                    ->where('pb.quantity_remaining', '>', 0),
                'pb.branch_id'
            )->distinct('pb.drug_id')->count('pb.drug_id');

            $todaySales = $scope(DB::table('issued_items'))
                ->where('issue_date', Carbon::today()->toDateString())
                ->sum('total_amount');

            $totalProducts = $scope(DB::table('purchase_batches'))
                ->whereNotNull('product_id')
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query
                    ->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', Carbon::today()))
                ->distinct()
                ->count('product_id');
            $productLowStockCount = DB::query()->fromSub(
                DB::table('products as p')
                    ->leftJoin('purchase_batches as pb', function ($join) use ($branchId) {
                        $join->on('p.id', '=', 'pb.product_id');
                        $join->where(fn ($q) => $q
                            ->whereNull('pb.expiry_date')
                            ->orWhere('pb.expiry_date', '>=', Carbon::today()->toDateString()));
                        if ($branchId) {
                            $join->where('pb.branch_id', $branchId);
                        }
                    })
                    ->select('p.id')
                    ->groupBy('p.id', 'p.minimum_stock', 'p.units_per_top')
                    ->havingRaw('COALESCE(SUM(pb.quantity_remaining), 0) <= p.minimum_stock * p.units_per_top'),
                'low_stock_products'
            )->count();
            $lowStockCount += $productLowStockCount;

            // Only what the dashboard actually renders. The "Today's pulse"
            // card used to pull stock value, 7-day sales and purchases and a
            // list of expiring drugs; with that card gone those queries were
            // dead weight on every cold load.
            return [
                'totalDrugs' => $totalDrugs,
                'lowStockCount' => $lowStockCount,
                'todaySales' => $todaySales,
                'expiringSoonCount' => $expiringSoonCount,
                'totalProducts' => $totalProducts,
            ];
        });

        $supplierReminders = null;
        $billsDue = collect();
        if ($user->role === 'owner_admin') {
            $alertDays = max(1, (int) \App\Models\Setting::value('supplier_alert_days', '7'));

            $supplierReminders = app(\App\Services\SupplierOwedService::class)
                ->reminderData($branchId, $alertDays);

            // Monthly bills due (or overdue). Same helper as the Alerts page
            // and the sidebar badge, so the three can never disagree.
            try {
                $billsDue = \App\Models\Expense::dueBillsCached($branchId, $alertDays);
            } catch (\Throwable $e) {
                $billsDue = collect();
            }
        }

        return view('dashboard.index', array_merge($data, [
            'supplierReminders' => $supplierReminders,
            'billsDue' => $billsDue,
            'expiryDays' => $expiryDays,
            // For the End shift button. The middleware already looked it up
            // for this request, so reuse that answer when it is there.
            'shift' => $request->attributes->get('current_shift')
                ?? \App\Models\ShiftOpening::currentFor(
                    $user,
                    $branchId ?: \App\Models\Branch::query()->orderBy('id')->value('id')
                ),
            // Only the owner can take a backup, so only the owner is told
            // about a lapsed one. Null means nothing to say.
            'backupWarning' => $user->role === 'owner_admin' ? BackupHealth::message() : null,
            'shortcuts' => $this->shortcutsFor($user),
            'shortcutChoices' => $this->availableShortcutsFor($user),
        ]));
    }

    /**
     * The shortcuts to render, already resolved to URLs and tinted.
     *
     * Keys the user saved but that no longer exist (or that their role can no
     * longer open) are dropped rather than rendered as dead links.
     *
     * @return array<int, array<string, string>>
     */
    protected function shortcutsFor($user): array
    {
        $saved = $user->dashboard_shortcuts;

        // An explicit list is honoured even when it resolves to nothing - if
        // someone cleared every tile, that was a decision, not a bug. Only a
        // user who has never opened the picker gets the defaults.
        if (is_array($saved)) {
            return $this->resolveShortcuts($user, $saved);
        }

        return $this->resolveShortcuts($user, self::DEFAULT_SHORTCUTS);
    }

    /** @return array<int, array<string, string>> */
    protected function availableShortcutsFor($user): array
    {
        return $this->resolveShortcuts($user, array_keys(self::AVAILABLE_SHORTCUTS));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, string>>
     */
    protected function resolveShortcuts($user, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $shortcut = self::AVAILABLE_SHORTCUTS[$key] ?? null;

            if (! $shortcut || ! $this->roleAllows($user, $shortcut)) {
                continue;
            }

            // route() returns a full URL; the view wants a path for its <a>.
            $out[] = [
                'key' => $key,
                'label' => $shortcut['label'],
                'url' => route($shortcut['route']),
                'classes' => $shortcut['classes'],
            ];
        }

        return $out;
    }

    protected function roleAllows($user, array $shortcut): bool
    {
        if (! isset($shortcut['roles'])) {
            return true;
        }

        return in_array($user->role, $shortcut['roles'], true);
    }

    /**
     * Add or remove shortcuts. Only keys that exist and that this role is
     * allowed to open are stored, so a hand-crafted post cannot plant a link
     * to a screen the person cannot see.
     */
    public function updateShortcuts(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'shortcuts' => ['nullable', 'array'],
            'shortcuts.*' => ['string'],
        ]);

        $chosen = array_values(array_filter(
            (array) ($validated['shortcuts'] ?? []),
            fn ($key) => isset(self::AVAILABLE_SHORTCUTS[$key])
                && $this->roleAllows($user, self::AVAILABLE_SHORTCUTS[$key])
        ));

        $user->dashboard_shortcuts = $chosen;
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Shortcuts updated.');
    }

    /** Drop one shortcut without opening the picker. */
    public function removeShortcut(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'shortcut' => ['required', 'string'],
        ]);

        $current = is_array($user->dashboard_shortcuts)
            ? $user->dashboard_shortcuts
            : self::DEFAULT_SHORTCUTS;

        $user->dashboard_shortcuts = array_values(array_diff($current, [$validated['shortcut']]));
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Shortcut removed.');
    }

    /** Put the three defaults back. */
    public function resetShortcuts(Request $request)
    {
        $request->user()->dashboard_shortcuts = null;
        $request->user()->save();

        return redirect()->route('dashboard')->with('success', 'Shortcuts reset to the default three.');
    }
}