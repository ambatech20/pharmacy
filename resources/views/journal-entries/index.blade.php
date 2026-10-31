@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Journal Entries</h1>
    <a href="{{ route('journal-entries.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ New Manual Entry</a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Voucher #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Source</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Credit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($entries as $entry)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-sm text-gray-700">{{ $entry->voucher_number }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ \Carbon\Carbon::parse($entry->date)->format('M d, Y') }}</td>
                    <td class="px-4 py-3">{{ $entry->description }}</td>
                    <td class="px-4 py-3">
                        <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-1 rounded-full">
                            {{ ucfirst($entry->reference_type ?? 'manual') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($entry->total_debit, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($entry->total_credit, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        No journal entries yet. <a href="{{ route('journal-entries.create') }}" class="text-blue-600 underline">Post your first one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $entries->links() }}
</div>
@endsection
