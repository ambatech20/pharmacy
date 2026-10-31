@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">New Manual Journal Entry</h1>

    <form method="POST" action="{{ route('journal-entries.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input type="text" name="description" value="{{ old('description') }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>

        {{-- Single-branch install: no branch is chosen here; the entry is
             stamped with the pharmacy's one branch. --}}

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Lines</label>

            <div id="lines-container" class="space-y-2">
                {{-- Two starter rows — a balanced entry needs at least a debit and a credit line --}}
                @for ($i = 0; $i < 2; $i++)
                    <div class="grid grid-cols-12 gap-2 items-center line-row">
                        <div class="col-span-6">
                            <select name="lines[{{ $i }}][account_id]" class="w-full border border-gray-300 rounded px-2 py-2 text-sm" required>
                                <option value="">-- Account --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-3">
                            <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" placeholder="Debit"
                                   class="w-full border border-gray-300 rounded px-2 py-2 text-sm">
                        </div>
                        <div class="col-span-3">
                            <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" placeholder="Credit"
                                   class="w-full border border-gray-300 rounded px-2 py-2 text-sm">
                        </div>
                    </div>
                @endfor
            </div>

            <button type="button" id="add-line-btn" class="mt-3 text-blue-600 text-sm font-semibold hover:underline">
                + Add another line
            </button>

            <p class="text-xs text-gray-500 mt-2">Total debits must equal total credits before this can be posted.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Post Entry
            </button>
            <a href="{{ route('journal-entries.index') }}" class="text-gray-600 py-2 px-4">Cancel</a>
        </div>
    </form>
</div>

{{-- Vanilla JS — no extra frontend framework dependency assumed --}}
<script>
    (function () {
        const container = document.getElementById('lines-container');
        const addBtn = document.getElementById('add-line-btn');
        let lineIndex = {{ 2 }};

        const accountOptions = `@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach`;

        addBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 items-center line-row';
            row.innerHTML = `
                <div class="col-span-6">
                    <select name="lines[${lineIndex}][account_id]" class="w-full border border-gray-300 rounded px-2 py-2 text-sm" required>
                        <option value="">-- Account --</option>
                        ${accountOptions}
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="number" step="0.01" min="0" name="lines[${lineIndex}][debit]" placeholder="Debit"
                           class="w-full border border-gray-300 rounded px-2 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <input type="number" step="0.01" min="0" name="lines[${lineIndex}][credit]" placeholder="Credit"
                           class="w-full border border-gray-300 rounded px-2 py-2 text-sm">
                </div>
                <div class="col-span-1 text-center">
                    <button type="button" class="remove-line-btn text-red-500 text-lg leading-none">&times;</button>
                </div>
            `;
            container.appendChild(row);
            lineIndex++;
        });

        container.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-line-btn')) {
                e.target.closest('.line-row').remove();
            }
        });
    })();
</script>
@endsection
