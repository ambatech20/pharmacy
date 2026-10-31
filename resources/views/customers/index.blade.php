@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Customers</h1>
    <a href="{{ route('customers.create') }}" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-4 rounded-lg text-sm">+ Add New Customer</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Phone</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Email</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Recurring</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($customers as $customer)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 font-medium text-[#1E2530]">{{ $customer->name }}</td>
                    <td class="px-5 py-3.5 text-[#64748b]">{{ $customer->phone ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-[#64748b]">{{ $customer->email ?? '—' }}</td>
                    <td class="px-5 py-3.5">
                        @if($customer->is_recurring)
                            <span class="bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-xs font-semibold px-2 py-1 rounded-full">Every {{ $customer->reminder_interval_days }}d</span>
                        @else
                            <span class="text-[#64748b] text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <a href="{{ route('customers.edit', $customer->id) }}" class="text-[var(--brand-primary)] hover:underline mr-3">Edit</a>
                        <form action="{{ route('customers.destroy', $customer->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete {{ $customer->name }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-[#64748b]">No customers yet. <a href="{{ route('customers.create') }}" class="text-[var(--brand-primary)] underline">Add your first one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
