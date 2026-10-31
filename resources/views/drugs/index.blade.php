@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div><h1 class="text-2xl font-bold text-[#1E2530]">Drugs</h1><p class="text-sm text-[#64748b] mt-1">Search by name, generic name, strength, or category.</p></div>
    <a href="{{ route('drugs.create') }}" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-4 rounded-lg text-sm">+ Add New Drug</a>
</div>

<form method="GET" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3">
    <input name="q" value="{{ request('q') }}" placeholder="Search drugs" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="flex-1 min-w-[220px] border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
    <select name="category_id" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select>
    <select name="kind" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
        <option value="">All types</option>
        @foreach(\App\Models\Drug::KINDS as $value => $label)
            <option value="{{ $value }}" @selected(request('kind') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="bg-[#10253b] text-white rounded-lg px-4 py-2.5 text-sm font-medium">Search</button>
    @if(request()->filled('q') || request()->filled('category_id') || request()->filled('kind'))<a href="{{ route('drugs.index') }}" class="rounded-lg px-4 py-2.5 text-sm text-[#64748b] hover:bg-gray-100">Clear</a>@endif
</form>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Generic Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Strength</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Category</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Unit</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Current Stock</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($drugs as $drug)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $drug->name }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="text-xs font-semibold px-2 py-1 rounded-full {{ match($drug->kind) {
                            'medical_supply' => 'bg-blue-100 text-blue-700',
                            'supplement' => 'bg-emerald-100 text-emerald-700',
                            'infant_formula' => 'bg-amber-100 text-amber-700',
                            default => 'bg-purple-100 text-purple-700',
                        } }}">
                            {{ \App\Models\Drug::KINDS[$drug->kind] ?? 'Medicine' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $drug->generic_name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $drug->strength ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-2 py-1 rounded-full">{{ $drug->category->name ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">
                        <div>{{ $drug->packingSummary() }}</div>
                        @php $soldAs = collect($drug->sellableLevels())->pluck('name')->map(fn ($name) => ucfirst($name)); @endphp
                        <div class="mt-0.5 text-xs text-[#94a3b8]">
                            {{ $soldAs->isEmpty() ? 'Nothing ticked to sell' : 'Sells: ' . $soldAs->implode(', ') }}
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right {{ $drug->isBelowMinimum($drug->current_stock ?? 0) ? 'text-amber-600 font-semibold' : 'text-[#1E2530]' }}" title="{{ $drug->describeQuantity($drug->current_stock ?? 0) }}">{{ $drug->describeStock($drug->current_stock ?? 0) }}</td>
                    <td class="px-5 py-3.5 text-sm text-right whitespace-nowrap">
                        <a href="{{ route('drugs.edit', $drug->id) }}" class="text-[var(--brand-primary)] hover:underline mr-3">Edit</a>
                        <form action="{{ route('drugs.destroy', $drug->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete {{ $drug->name }}? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-sm text-[#64748b]">
                        No drugs yet. <a href="{{ route('drugs.create') }}" class="text-[var(--brand-primary)] underline">Add your first one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $drugs->links() }}</div>
@endsection
