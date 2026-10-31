@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Branches</h1>
    <a href="{{ route('branches.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ Add New Branch</a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Address</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($branches as $branch)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">{{ $branch->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $branch->address ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $branch->phone ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">
                        No branches yet. <a href="{{ route('branches.create') }}" class="text-blue-600 underline">Add your first one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
