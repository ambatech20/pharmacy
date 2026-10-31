@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Add User</h1>
    <p class="text-sm text-[#64748b] mb-6">Create a staff login and choose the branch where they work.</p>

    <form method="POST" action="{{ route('users.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Username (used to log in)</label>
            <input type="text" name="username" value="{{ old('username') }}" autocomplete="off"
                   autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
            <p class="text-xs text-[#64748b] mt-1">Letters, numbers, dots, dashes and underscores. Not case-sensitive — stored in lower case.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Email (stored on the account)</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
            <p class="text-xs text-[#64748b] mt-1">Kept for your records. Staff sign in with their username, not this email.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Password</label>
                <input type="password" name="password" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Confirm Password</label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Staff role</label>
            <select name="role" id="role-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                <option value="">-- Select Role --</option>
                @foreach($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role') == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div id="branch-field">
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Assigned branch <span id="branch-required" class="text-red-500">*</span></label>
            <select name="branch_id" id="branch-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                <option value="">-- Select Branch --</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-[#64748b] mt-1">Owner / Admin may stay unassigned for all-branch access. Every other role needs a branch.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Create User
            </button>
            <a href="{{ route('users.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    // Purely cosmetic — dims the branch field for Owner/Admin since it's
    // optional for that role. Server-side validation is the real source
    // of truth (see UserController::store), this is just a visual hint.
    (function () {
        const roleSelect = document.getElementById('role-select');
        const branchField = document.getElementById('branch-field');

        const branchSelect = document.getElementById('branch-select');
        const requiredMark = document.getElementById('branch-required');
        function updateBranchField() {
            const isOwner = roleSelect.value === 'owner_admin';
            branchField.style.opacity = isOwner ? '0.65' : '1';
            branchSelect.required = !isOwner;
            requiredMark.classList.toggle('hidden', isOwner);
        }

        roleSelect.addEventListener('change', updateBranchField);
        updateBranchField();
    })();
</script>
@endsection
