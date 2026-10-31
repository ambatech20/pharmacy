@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Activity Log</h1>
    <p class="text-sm text-[#64748b] mt-1">Who changed what, and when.</p>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
    <select name="user_id" class="rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All staff</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select>
    <select name="action" class="rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All actions</option>@foreach(['created' => 'Added', 'updated' => 'Updated', 'deleted' => 'Deleted', 'backup' => 'Downloaded'] as $value => $label)<option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>@endforeach</select>
    <select name="model_type" class="rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All records</option>@foreach($recordTypes as $type)<option value="{{ $type }}" @selected(request('model_type') === $type)>{{ $type }}</option>@endforeach</select>
    <button class="rounded-lg bg-[#10253b] px-4 py-2 text-sm font-medium text-white">Filter log</button>
    @if(request()->hasAny(['user_id', 'action', 'model_type']))<a href="{{ route('activity-logs.index') }}" class="rounded-lg px-4 py-2 text-sm text-[#64748b] hover:bg-gray-100">Clear</a>@endif
</form>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">When</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">User</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Action</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">What changed</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Details</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#64748b] whitespace-nowrap">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $log->user->name ?? 'System' }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $log->action === 'created' ? 'bg-emerald-50 text-emerald-700' : ($log->action === 'deleted' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600') }}">
                            {{ ['created' => 'Added', 'updated' => 'Updated', 'deleted' => 'Deleted', 'backup' => 'Downloaded'][$log->action] ?? ucfirst($log->action) }}
                        </span>
                    </td>
                    @php
                        $recordNames = ['Drug' => 'Drug', 'Product' => 'Product', 'DrugCategory' => 'Drug category', 'ProductCategory' => 'Product category', 'PurchaseBatch' => 'Stock batch', 'IssuedItem' => 'Sale item', 'Order' => 'Sale', 'User' => 'Staff account', 'Branch' => 'Branch', 'Supplier' => 'Supplier', 'Customer' => 'Customer', 'StockAdjustment' => 'Stock adjustment', 'CustomerReturn' => 'Customer return', 'SupplierReturn' => 'Supplier return', 'Payment' => 'Payment', 'Backup' => 'Database backup'];
                        $fieldNames = ['name' => 'Name', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role', 'branch_id' => 'Branch', 'quantity_remaining' => 'Stock remaining', 'quantity_received' => 'Stock received', 'quantity_issued' => 'Quantity sold', 'selling_price_per_unit' => 'Selling price', 'cost_per_unit' => 'Cost price', 'active' => 'Status', 'sku' => 'SKU', 'category_id' => 'Category', 'total_amount' => 'Amount', 'password' => 'Password', 'file' => 'File', 'tables' => 'Tables', 'rows' => 'Rows', 'kilobytes' => 'Size (KB)'];
                        $details = collect($log->changes ?? [])->map(function ($value, $key) use ($fieldNames) {
                            $label = $fieldNames[$key] ?? ucfirst(str_replace('_', ' ', $key));
                            if ($key === 'active') $value = $value ? 'Active' : 'Inactive';
                            if (is_array($value)) $value = implode(', ', $value);
                            return $label . ': ' . ($value === null || $value === '' ? 'None' : $value);
                        })->implode(' · ');
                    @endphp
                    <td class="px-5 py-3.5 text-sm text-[#1E2530] font-medium">{{ $recordNames[$log->model_type] ?? $log->model_type }}@if($log->model_id) <span class="text-[#64748b] font-normal">#{{ $log->model_id }}</span>@endif</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b] max-w-md">{{ $details ?: 'No extra details recorded.' }}</td>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-sm text-[#64748b]">No activity recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
