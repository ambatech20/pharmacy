@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Chart of Accounts</h1>
    <a href="{{ route('accounts.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ Add New Account</a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($accounts as $account)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-700">{{ $account->code }}</td>
                    <td class="px-4 py-3 font-medium">{{ $account->name }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold px-2 py-1 rounded-full
                            @class([
                                'bg-green-100 text-green-700' => $account->type === 'asset',
                                'bg-red-100 text-red-700' => $account->type === 'liability',
                                'bg-blue-100 text-blue-700' => $account->type === 'equity',
                                'bg-purple-100 text-purple-700' => $account->type === 'revenue',
                                'bg-orange-100 text-orange-700' => $account->type === 'expense',
                            ])">
                            {{ ucfirst($account->type) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($account->active)
                            <span class="text-green-600 text-sm">● Active</span>
                        @else
                            <span class="text-gray-400 text-sm">● Inactive</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                        No accounts yet. <a href="{{ route('accounts.create') }}" class="text-blue-600 underline">Add your first one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
