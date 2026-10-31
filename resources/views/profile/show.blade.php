@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-6">My Profile</h1>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center gap-4 mb-6">
            <span class="w-14 h-14 rounded-full bg-[var(--brand-accent)] flex items-center justify-center text-white text-xl font-semibold">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </span>
            <div>
                <p class="font-bold text-[#1E2530] text-lg">{{ $user->name }}</p>
                <p class="text-sm text-[#64748b]">{{ $user->username ?? $user->email ?? '' }}</p>
            </div>
        </div>

        <p class="text-xs text-[#64748b] mt-6">
            Need access changed, or another staff account added? Ask your Owner/Admin.
        </p>
    </div>
</div>
@endsection
