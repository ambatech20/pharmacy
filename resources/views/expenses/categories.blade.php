@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Expense Categories</h1>
    <a href="{{ route('expenses.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm min-h-[44px] inline-flex items-center">← Back to Expenses</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Expenses using it</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($categories as $cat)
                    <tr class="hover:bg-gray-50/50">
                        <form method="POST" action="{{ route('expense-categories.update', $cat->id) }}">
                            @csrf @method('PUT')
                            <td class="px-5 py-3">
                                <input type="text" name="name" value="{{ $cat->name }}" class="w-full max-w-[220px] rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px] text-sm font-medium">
                            </td>
                            <td class="px-5 py-3 text-[#64748b]">{{ $cat->expenses_count }}</td>
                            <td class="px-5 py-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer min-h-[44px]">
                                    <input type="checkbox" name="active" value="1" class="rounded accent-[var(--brand-primary)] w-5 h-5" @checked($cat->active)>
                                    <span class="text-sm">{{ $cat->active ? 'Active' : 'Turned off' }}</span>
                                </label>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-semibold py-2 px-4 rounded-lg text-sm min-h-[44px]">Save</button>
                            </td>
                        </form>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        <p class="text-xs text-[#64748b] mt-3">Turning a category off hides it from the Add Expense form but keeps old expenses intact.</p>
    </div>

    <div>
        <form method="POST" action="{{ route('expense-categories.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            @csrf
            <h2 class="text-lg font-bold text-[#1E2530]">Add a category</h2>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Cleaning"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-4 rounded-lg min-h-[44px]">Add Category</button>
        </form>
    </div>
</div>
@endsection
