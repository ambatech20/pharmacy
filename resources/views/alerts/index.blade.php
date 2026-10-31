@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Alerts</h1>
    <p class="text-sm text-[#64748b] mt-1">Expiry warnings and customers due for a refill reminder.</p>
</div>

<div class="space-y-6">

    {{-- PART B — Supplier payment reminders (owner/admin only, live from purchases) --}}
    @if(!empty($supplierReminders))
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div><h2 class="font-bold text-[#1E2530]">Owed to suppliers — Overdue</h2><p class="text-xs text-[#64748b] mt-0.5">Past their due date. Pay these first.</p></div>
            </div>
            @if(count($supplierReminders['overdue']))
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                        @foreach($supplierReminders['overdue'] as $item)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $item['supplier_name'] }}</td>
                                <td class="px-5 py-3 text-xs text-[#64748b]">{{ $item['supplier_invoice_no'] ?: $item['purchase_ref'] }}</td>
                                <td class="px-5 py-3 text-sm text-right text-red-600 font-semibold">ETB {{ number_format($item['balance'], 2) }}</td>
                                <td class="px-5 py-3 text-sm text-right text-red-600">Due {{ $item['due_date']->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('supplier-payments.create', $item['supplier_id']) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">Pay supplier</a></td>
                            </tr>
                        @endforeach
                    </tbody></table>
                </div>
            @else
                <p class="px-5 py-6 text-sm text-[#64748b]">Nothing is overdue. Good.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 font-bold">7</span>
                <div><h2 class="font-bold text-[#1E2530]">Payments due within {{ $supplierReminders['soon_days'] }} days</h2><p class="text-xs text-[#64748b] mt-0.5">Get ready to pay these suppliers.</p></div>
            </div>
            @if(count($supplierReminders['due_soon']))
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                        @foreach($supplierReminders['due_soon'] as $item)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $item['supplier_name'] }}</td>
                                <td class="px-5 py-3 text-xs text-[#64748b]">{{ $item['supplier_invoice_no'] ?: $item['purchase_ref'] }}</td>
                                <td class="px-5 py-3 text-sm text-right text-amber-600 font-semibold">ETB {{ number_format($item['balance'], 2) }}</td>
                                <td class="px-5 py-3 text-sm text-right text-amber-600">Due {{ $item['due_date']->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('supplier-payments.create', $item['supplier_id']) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">Pay supplier</a></td>
                            </tr>
                        @endforeach
                    </tbody></table>
                </div>
            @else
                <p class="px-5 py-6 text-sm text-[#64748b]">No supplier payments are due in the next {{ $supplierReminders['soon_days'] }} days.</p>
            @endif
        </div>
    @endif

    {{-- PART C — Monthly repeating bills due soon (owner/admin only) --}}
    @if(!empty($recurringExpensesDue) && $recurringExpensesDue->count())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-sky-50 flex items-center justify-center text-sky-600 font-bold">↻</span>
                <div><h2 class="font-bold text-[#1E2530]">Monthly bills due now</h2><p class="text-xs text-[#64748b] mt-0.5">Repeating expenses that need to be paid this month.</p></div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                    @foreach($recurringExpensesDue as $bill)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $bill->category->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-right text-[#1E2530] font-semibold">ETB {{ number_format($bill->reminderAmount(), 2) }}@if($bill->paid_months > 1)<span class="block text-xs font-normal text-[#64748b]">per month</span>@endif</td>
                            <td class="px-5 py-3 text-sm text-right">
                                @if($bill->isOverdue())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Overdue</span>
                                    <span class="block text-xs text-red-700 mt-0.5">due {{ $bill->reminderDate()?->format('d M Y') }}</span>
                                @else
                                    <span class="text-sky-700">Due {{ $bill->reminderDate()?->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('expenses.create', ['bill' => $bill->id]) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">Record payment</a></td>
                        </tr>
                    @endforeach
                </tbody></table>
            </div>
        </div>
    @endif

    {{-- Low Stock --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 font-bold">!</span>
            <div><h2 class="font-bold text-[#1E2530]">Low Stock — Restock Soon</h2><p class="text-xs text-[#64748b] mt-0.5">Items at or below their minimum stock level.</p></div>
        </div>
        @if($lowStockDrugs->count() || $lowStockProducts->count())
            <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                @foreach($lowStockDrugs as $drug)
                    <tr class="hover:bg-gray-50/50"><td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $drug->name }}</td><td class="px-5 py-3 text-xs text-[#64748b]">Drug</td><td class="px-5 py-3 text-sm text-right text-amber-600">{{ $drug->describeQuantity($drug->total_stock) }} left <span class="text-xs text-[#64748b]">/ minimum {{ number_format((float) $drug->minimum_stock_level, 0) }} {{ \Illuminate\Support\Str::plural($drug->topUnitLabel(), (int) $drug->minimum_stock_level) }}</span></td><td class="px-5 py-3 text-right"><a href="{{ route('drugs.index', ['q' => $drug->name]) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">View drug</a></td></tr>
                @endforeach
                @foreach($lowStockProducts as $product)
                    <tr class="hover:bg-gray-50/50"><td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $product->name }}</td><td class="px-5 py-3 text-xs text-[#64748b]">Product</td><td class="px-5 py-3 text-sm text-right text-amber-600">{{ $product->describeQuantity($product->total_stock) }} left <span class="text-xs text-[#64748b]">/ minimum {{ number_format((float) $product->minimum_stock, 0) }} {{ \Illuminate\Support\Str::plural($product->topUnitLabel(), (int) $product->minimum_stock) }}</span></td><td class="px-5 py-3 text-right"><a href="{{ route('products.index', ['q' => $product->name]) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">View product</a></td></tr>
                @endforeach
            </tbody></table>
        @else
            <p class="px-5 py-6 text-sm text-[#64748b]">No items are currently below their minimum stock level.</p>
        @endif
    </div>

    {{-- Expired --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h2 class="font-bold text-[#1E2530]">Expired Stock — Remove from Shelf</h2>
        </div>
        @if($expired->count() > 0)
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    @foreach($expired as $batch)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $batch->drug->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">Batch {{ $batch->batch_number }}</td>
                            <td class="px-5 py-3 text-sm text-right text-[#64748b]">{{ $batch->drug ? $batch->drug->describeQuantity($batch->quantity_remaining) : $batch->quantity_remaining . ' units' }}</td>
                            <td class="px-5 py-3 text-sm text-right">
                                <a href="{{ route('adjustments.create', ['batch_id' => $batch->id]) }}" class="text-[var(--brand-primary)] hover:underline text-xs font-medium">Write off</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="px-5 py-6 text-sm text-[#64748b]">No expired stock currently on hand.</p>
        @endif
    </div>

    {{-- Expiring Soon --}}
    <div id="expiring-soon" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center gap-x-3 gap-y-2">
            <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <div class="mr-auto">
                <h2 class="font-bold text-[#1E2530]">Expiring Within {{ $expiryDays }} Days</h2>
                <p class="text-xs text-[#64748b] mt-0.5">Stock on the shelf that runs out within the window you choose.</p>
            </div>
            <div class="inline-flex items-center gap-0.5 rounded-lg border border-gray-200 p-0.5" role="group" aria-label="Expiry alert window">
                @foreach(\App\Models\Setting::EXPIRY_ALERT_WINDOWS as $window)
                    <a href="{{ route('alerts.index', ['expiry' => $window]) }}#expiring-soon"
                       class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $expiryDays === $window ? 'bg-[var(--brand-primary)] text-white' : 'text-[#64748b] hover:bg-gray-50' }}">{{ $window }} days</a>
                @endforeach
            </div>
        </div>
        @if($expiringSoon->count() > 0)
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    @foreach($expiringSoon as $batch)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $batch->drug->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">Batch {{ $batch->batch_number }}</td>
                            <td class="px-5 py-3 text-sm text-right text-[#64748b]">{{ $batch->drug ? $batch->drug->describeQuantity($batch->quantity_remaining) : $batch->quantity_remaining . ' units' }}</td>
                            <td class="px-5 py-3 text-sm text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">
                                    {{ \Carbon\Carbon::parse($batch->expiry_date)->format('M d, Y') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="px-5 py-6 text-sm text-[#64748b]">Nothing expiring in the next {{ $expiryDays }} days.</p>
        @endif
    </div>

    {{-- Customers Due for Refill --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-[var(--brand-primary)]/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[var(--brand-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </svg>
            </span>
            <h2 class="font-bold text-[#1E2530]">Customers Due for Refill</h2>
        </div>
        @if($dueForRefill->count() > 0)
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    @foreach($dueForRefill as $customer)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">{{ $customer->name }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">{{ $customer->condition_note ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-right text-[#64748b]">{{ $customer->phone ?? 'no phone on file' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="px-5 py-6 text-sm text-[#64748b]">No recurring customers due for a reminder right now.</p>
        @endif
    </div>

</div>
@endsection
