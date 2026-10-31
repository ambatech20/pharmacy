<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DispenseController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\DrugCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TillClosingController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\BatchPriceController;
use App\Http\Controllers\BackupController;

// --- Public Routes (Login) ---
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
// Generated so the installed PWA picks up the pharmacy's own branding.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:5,1');

// --- Shift opening: the drawer count that starts a shift ---
// Deliberately outside the 'shift' middleware below, or it would guard the
// very screen it sends people to.
Route::middleware(['auth'])->group(function () {
    Route::get('/shift/open', [ShiftController::class, 'create'])->name('shift.open');
    Route::post('/shift/open', [ShiftController::class, 'store'])->name('shift.store');
});

// --- Protected Routes (Requires Login) ---
// 'shift' makes every screen below wait until the drawer has been counted.
Route::middleware(['auth', 'shift'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

    // ============================================================
    // Dashboard shortcuts. Personal, so no role gate: every signed-in
    // person configures their own. The controller drops any key that
    // does not exist or that this role cannot open.
    // ============================================================
    Route::post('/dashboard/shortcuts', [DashboardController::class, 'updateShortcuts'])
        ->name('dashboard.shortcuts.update')->middleware('throttle:30,1');
    Route::post('/dashboard/shortcuts/remove', [DashboardController::class, 'removeShortcut'])
        ->name('dashboard.shortcuts.remove')->middleware('throttle:30,1');
    Route::post('/dashboard/shortcuts/reset', [DashboardController::class, 'resetShortcuts'])
        ->name('dashboard.shortcuts.reset');

    // ============================================================
    // TIER 1 — Any logged-in role (Owner/Admin, Branch Manager,
    // Pharmacist/Clerk). Day-to-day operational work.
    // ============================================================
    Route::middleware(['role:owner_admin,branch_manager,pharmacist_clerk'])->group(function () {

        // Purchases
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');

        // Dispensing
        Route::get('/dispense', [DispenseController::class, 'index'])->name('dispense.index');
        Route::get('/dispense/create', [DispenseController::class, 'create'])->name('dispense.create');
        Route::post('/dispense', [DispenseController::class, 'store'])->name('dispense.store');
        Route::get('/dispense/{order}', [DispenseController::class, 'show'])->name('dispense.show');
        Route::get('/dispense/{order}/receipt', [ReceiptController::class, 'show'])->name('dispense.receipt');
        Route::get('/dispense/{order}/receipt/escpos', [ReceiptController::class, 'escpos'])->name('dispense.receipt.escpos');
        Route::get('/search-drug', [DispenseController::class, 'searchDrug'])->name('search.drug');
        Route::get('/item-data', [DispenseController::class, 'itemData'])->name('item.data');

        Route::get('/till-closings', [TillClosingController::class, 'index'])->name('till-closings.index');
        Route::post('/till-closings', [TillClosingController::class, 'store'])->name('till-closings.store');

        Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');

        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/customer/create', [ReturnController::class, 'createCustomer'])->name('returns.customer.create');
        Route::post('/returns/customer', [ReturnController::class, 'storeCustomer'])->name('returns.customer.store');
        Route::get('/returns/supplier/create', [ReturnController::class, 'createSupplier'])->name('returns.supplier.create');
        Route::post('/returns/supplier', [ReturnController::class, 'storeSupplier'])->name('returns.supplier.store');

        // Master data (drugs, categories, suppliers, customers) —
        // clerks legitimately need to add a new drug or supplier while
        // recording an unfamiliar purchase, so this stays open to all.
        Route::get('/categories', [DrugCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [DrugCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [DrugCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [DrugCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [DrugCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [DrugCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/drugs', [DrugController::class, 'index'])->name('drugs.index');
        Route::get('/drugs/create', [DrugController::class, 'create'])->name('drugs.create');
        Route::post('/drugs', [DrugController::class, 'store'])->name('drugs.store');
        Route::get('/drugs/{drug}/edit', [DrugController::class, 'edit'])->name('drugs.edit');
        Route::put('/drugs/{drug}', [DrugController::class, 'update'])->name('drugs.update');
        Route::delete('/drugs/{drug}', [DrugController::class, 'destroy'])->name('drugs.destroy');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::get('/product-categories/create', [ProductCategoryController::class, 'create'])->name('product-categories.create');
        Route::post('/product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::get('/product-categories/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->name('product-categories.edit');
        Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    });

    // ============================================================
    // PART B — Owed to suppliers / Pay supplier. Owner/Admin only;
    // clerks and branch managers cannot see supplier debts or pay them.
    // Plain-language names: "Owed to suppliers", "Pay supplier".
    // ============================================================
    Route::middleware(['role:owner_admin'])->group(function () {
        // Deleting a purchase that was entered by mistake reverses its ledger
        // entry, so it sits with the other books-level actions.
        Route::delete('/purchases/{batch}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');

        Route::get('/supplier-owed', [SupplierPaymentController::class, 'index'])->name('supplier-owed.index');
        Route::get('/supplier-owed/{supplier}', [SupplierPaymentController::class, 'show'])->name('supplier-owed.show');
        Route::get('/supplier-payments/{supplier}/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
        Route::post('/supplier-payments/{supplier}', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
        Route::get('/supplier-payments/{supplier}/history', [SupplierPaymentController::class, 'history'])->name('supplier-payments.history');

        // ============================================================
        // PART C — Expenses. Owner/Admin only. Plain-language UI; the
        // posting (expense account up, paying method's account down)
        // happens behind the scenes in ExpenseController.
        // ============================================================
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');

        // ============================================================
        // PART D — Branding / business settings. Owner/Admin only.
        // Nothing about a specific pharmacy is hard-coded: changing
        // these settings re-brands the whole system.
        // ============================================================
        Route::get('/settings/branding', [SettingController::class, 'edit'])->name('settings.branding');
        Route::put('/settings/branding', [SettingController::class, 'update'])->name('settings.branding.update')->middleware('throttle:15,1');
        Route::get('/settings/integration', [SettingController::class, 'editIntegration'])->name('settings.integration');
        Route::put('/settings/integration', [SettingController::class, 'updateIntegration'])->name('settings.integration.update')->middleware('throttle:15,1');

        // ============================================================
        // Backups. Owner/Admin only, like the settings above: a backup
        // file contains every account in the system.
        //
        // The hosting panel gives no SSH access and no scheduled tasks,
        // and its own backup feature only writes to paid remote storage,
        // so this is how the pharmacy gets its data off the server.
        // ============================================================
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::get('/backups/download', [BackupController::class, 'download'])->name('backups.download')->middleware('throttle:10,1');
        // The filename travels in the query string, not the path.
        //
        // This host fronts the site with nginx, which answers any request whose
        // path ends in ".sql" with its own 403 before the application is
        // reached - so "/backups/pmas_2026-01-01_120000.sql" would never
        // arrive. A 403 is indistinguishable from a permissions problem, which
        // is exactly the kind of fault that goes unnoticed for months.
        Route::get('/backups/fetch', [BackupController::class, 'fetch'])
            ->name('backups.fetch')
            ->middleware('throttle:30,1');

        // ============================================================
        // Opening balances. Replaced by the shift-opening drawer count
        // (ShiftController): the cash figure is asked for at the start of
        // every shift, and the other lines - receivables, stock, payables -
        // were numbers the books could report on but not act on.
        // ============================================================
        // ============================================================
        // Batch prices. Owner/Admin only, for the same reason discounts
        // are: a price change is money. This screen backs the locked
        // price box on the sale screen, so anyone who can raise a price
        // here can raise what every future sale charges. Branch managers
        // keep the rest of Tier 2 (accounts, journals, reports) but do
        // not set prices.
        // ============================================================
        Route::get('/stock/prices', [BatchPriceController::class, 'index'])->name('batch-prices.index');
        Route::put('/stock/prices/{batch}', [BatchPriceController::class, 'update'])->name('batch-prices.update');
        Route::put('/stock/prices/{batch}/apply', [BatchPriceController::class, 'applyToItem'])->name('batch-prices.apply');
    });

    // ============================================================
    // TIER 2 — Branch Manager and Owner/Admin only. Financial
    // visibility and manual bookkeeping tools.
    // ============================================================
    Route::middleware(['role:owner_admin,branch_manager'])->group(function () {

        // ============================================================
        // Branch module — stock transfers and branch management. Kept
        // separate from the single-branch screens so a multi-branch
        // install can still move stock between its branches.
        // ============================================================
        Route::get('/transfers', [StockTransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [StockTransferController::class, 'store'])->name('transfers.store');

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');

        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');

        Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');

        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/export', [ReportController::class, 'exportForm'])->name('reports.export.form');
        Route::get('/reports/export/download', [ReportController::class, 'export'])->name('reports.export.download');
    });

    // ============================================================
    // TIER 3 — Owner/Admin only. Staff account management.
    // ============================================================
    Route::middleware(['role:owner_admin'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('throttle:15,1');

        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('throttle:15,1');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
});
