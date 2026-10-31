<?php

namespace App\Http\Controllers;

use App\Models\Branch;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // Looked up directly rather than via a $user->branch relationship —
        // avoids assuming that relation exists on the User model, given
        // we've hit a few "assumed relation isn't actually defined" bugs
        // already in this project.
        $branch = $user->branch_id ? Branch::find($user->branch_id) : null;

        return view('profile.show', compact('user', 'branch'));
    }
}
