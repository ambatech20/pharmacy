<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('branch')->orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        return view('users.create', ['branches' => $branches, 'roles' => User::ROLES]);
    }

    public function store(Request $request)
    {
        // Normalise before validating so the "unique" check and the stored
        // value always agree, whatever the database collation.
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => self::usernameRules(),
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
        ], self::usernameMessages());

        // Owner/Admin isn't tied to a single branch — null means all-branch
        // access. For any other role, a branch is required so the app's
        // existing auth()->user()->branch_id filtering works correctly.
        if ($validated['role'] !== User::ROLE_OWNER_ADMIN && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Branch is required for this role.'])->withInput();
        }

        User::create($validated + ['active' => true]);

        return redirect()->route('users.index')->with('success', 'Staff account created.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'branches' => Branch::orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => [...self::usernameRules(), Rule::unique('users', 'username')->ignore($user->id)],
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
            'active' => 'nullable|boolean',
        ], self::usernameMessages());
        // Single-branch installs can leave Owner/Admin unassigned (null means
        // "all branches"); every other role needs a branch for the existing
        // auth()->user()->branch_id filtering to work.
        if ($validated['role'] !== User::ROLE_OWNER_ADMIN && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Branch is required for this role.'])->withInput();
        }
        if (empty($validated['password'])) unset($validated['password']);
        $validated['active'] = $request->boolean('active');
        $user->update($validated);
        return redirect()->route('users.index')->with('success', 'Staff account updated.');
    }

    /**
     * Shared username rules: it is the login identifier, so it must be present,
     * reasonably short, and made of plain characters (a value that is safe to
     * type and to put in a URL). The unique half is added per-action.
     */
    protected static function usernameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'];
    }

    protected static function usernameMessages(): array
    {
        return [
            'username.required' => 'Please choose a username for this account.',
            'username.regex' => 'Usernames may only contain letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'username.min' => 'A username needs at least 3 characters.',
        ];
    }
}
