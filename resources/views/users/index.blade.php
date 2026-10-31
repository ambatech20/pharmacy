@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Users</h1>
        <p class="text-sm text-[#64748b] mt-1">Staff accounts, roles, and assigned branches.</p>
    </div>
    <a href="{{ route('users.create') }}"
       class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Add User
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Username</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Email</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Staff role</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Assigned branch</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Status</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($users as $user)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $user->name }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $user->username ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $user->email }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $user->role === 'owner_admin' ? 'bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]' : 'bg-gray-100 text-[#64748b]' }}">
                            {{ $user->roleLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $user->branch->name ?? 'All Branches' }}</td>
                    <td class="px-5 py-3.5 text-sm"><span class="rounded-full px-2 py-1 text-xs font-medium {{ $user->active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $user->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-5 py-3.5 text-right text-sm"><a href="{{ route('users.edit', $user) }}" class="text-[var(--brand-primary)] hover:underline">Edit</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-sm text-[#64748b]">No users yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
