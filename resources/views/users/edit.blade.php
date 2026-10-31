@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Edit Staff Account</h1>
    <p class="text-sm text-[#64748b] mb-6">Update access, role, or account status.</p>
    <form method="POST" action="{{ route('users.update', $user) }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf @method('PUT')
        <div><label class="block text-sm font-medium mb-1.5">Full name</label><input name="name" value="{{ old('name', $user->name) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div>
        <div><label class="block text-sm font-medium mb-1.5">Username (used to log in)</label><input name="username" value="{{ old('username', $user->username) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><p class="text-xs text-[#64748b] mt-1">Not case-sensitive — stored in lower case.</p></div>
        <div><label class="block text-sm font-medium mb-1.5">Email (stored on the account)</label><input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div>
        <div><label class="block text-sm font-medium mb-1.5">New password <span class="font-normal text-[#64748b]">(leave blank to keep current)</span></label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" autocomplete="new-password"></div>
        <div><label class="block text-sm font-medium mb-1.5">Confirm new password</label><input type="password" name="password_confirmation" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" autocomplete="new-password"></div>
        <div><label class="block text-sm font-medium mb-1.5">Staff role</label><select name="role" id="role-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="block text-sm font-medium mb-1.5">Assigned branch</label><select name="branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="">-- All branches (Owner / Admin) --</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select><p class="text-xs text-[#64748b] mt-1">Owner / Admin may stay unassigned. Every other role needs a branch.</p></div>
        <label class="flex items-center gap-2 border-t border-gray-100 pt-4 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Account is active and can sign in</label>
        <div class="flex gap-3 pt-2"><button class="bg-[var(--brand-primary)] text-white font-medium text-sm py-2.5 px-5 rounded-lg">Save Changes</button><a href="{{ route('users.index') }}" class="text-[#64748b] py-2.5 px-4">Cancel</a></div>
    </form>
</div>
@endsection
