@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#1E2530]">End shift</h1>
        <p class="text-sm text-[#64748b] mt-1">Count the drawer one last time. Recording this closes your shift.</p>
    </div>

    @if(!$branchId && $branches->isNotEmpty())
        <form method="GET" action="{{ route('till-closings.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
            <label class="block text-sm font-medium text-[#1E2530] mb-2">Branch</label>
            <div class="flex gap-3">
                <select name="branch_id" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">-- Select Branch --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2 px-4 rounded-lg">Continue</button>
            </div>
            <p class="text-xs text-[#64748b] mt-2">Owner/Admin accounts are not tied to one till — pick the branch whose drawer you are counting.</p>
        </form>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-8">
            <p class="text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-3">This shift</p>
            <p class="text-sm text-[#64748b] mb-4">
                From {{ $periodStart->format('M d, Y g:i A') }} to now
            </p>

            {{-- Spelled out, because a single "expected" number hides the one
                 thing that goes wrong most: a mismatch between what the shift
                 started with and what the books think. --}}
            <dl class="mb-4 divide-y divide-gray-100 border-y border-gray-100">
                <div class="flex justify-between items-baseline py-2">
                    <dt class="text-sm text-[#64748b]">
                        Cash you started this shift with
                        @if($shift)
                            <span class="block text-xs text-[#8a98a8]">Counted {{ $shift->opened_at->format('g:i A') }}</span>
                        @else
                            <span class="block text-xs text-red-600">No shift-opening count recorded.</span>
                        @endif
                    </dt>
                    <dd class="text-sm font-semibold text-[#1E2530]">ETB {{ number_format($openingCash, 2) }}</dd>
                </div>
                <div class="flex justify-between items-baseline py-2">
                    <dt class="text-sm text-[#64748b]">
                        Cash taken during the shift
                        <span class="block text-xs text-[#64748b]">Cash sales, less cash refunds</span>
                    </dt>
                    <dd class="text-sm font-semibold text-[#1E2530]">ETB {{ number_format($collected, 2) }}</dd>
                </div>
                <div class="flex justify-between items-baseline py-2">
                    <dt class="text-sm font-semibold text-[#1E2530]">So the drawer should hold</dt>
                    <dd class="text-2xl font-bold text-[var(--brand-primary)]">ETB {{ number_format($expectedCash, 2) }}</dd>
                </div>
            </dl>
            <p class="text-xs text-[#64748b] mb-4">Cash-method payments only. Digital payments never enter the drawer.</p>

            @if(!empty($otherPayments))
                <div class="border border-gray-100 rounded-lg p-3 mb-5 bg-gray-50/50">
                    <p class="text-xs font-medium text-[#64748b] mb-2">Also collected this shift (not in till)</p>
                    <ul class="space-y-1 text-sm">
                        @foreach($otherPayments as $method => $amount)
                            <li class="flex justify-between">
                                <span class="text-[#64748b]">{{ \App\Models\Payment::METHODS[$method] ?? $method }}</span>
                                <span class="text-[#1E2530]">${{ number_format($amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('till-closings.store') }}" class="space-y-4">
                @csrf
                @if(!$branches->isEmpty())
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                @endif

                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1">Counted cash</label>
                    <input type="number" step="0.01" min="0" name="counted_cash" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                           placeholder="What is in the drawer?">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1">Note (optional)</label>
                    <input type="text" name="notes" maxlength="500"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                           placeholder="e.g. short a 50 note">
                </div>
                <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                    End shift
                </button>
                <p class="text-xs text-[#64748b]">
                    Any difference between counted and expected cash is posted to Cash Over/Short in the accounts.
                </p>
            </form>
        </div>
    @endif

    <h2 class="text-sm font-semibold text-[#64748b] uppercase tracking-wide mb-3">History</h2>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Closed</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Cashier</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Expected</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Counted</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Variance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($closings as $closing)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $closing->closed_at->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $closing->user->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">${{ number_format($closing->expected_cash, 2) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">${{ number_format($closing->counted_cash, 2) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-semibold {{ (float) $closing->variance === 0.0 ? 'text-[var(--brand-primary)]' : ((float) $closing->variance < 0 ? 'text-red-600' : 'text-amber-600') }}">
                            {{ (float) $closing->variance > 0 ? '+' : '' }}{{ number_format($closing->variance, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-[#64748b]">No till closes recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $closings->links() }}
    </div>
</div>
@endsection
