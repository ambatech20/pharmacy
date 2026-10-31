<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        // Usernames are stored trimmed and lower-case (see User model), so
        // normalise the typed value the same way before matching.
        $credentials['username'] = Str::lower(trim($credentials['username']));

        // Per-credential+IP throttle on top of the routes' flat throttle:5,1, so
        // an attack that rotates source IPs still cannot hammer one account.
        $throttleKey = Str::transliterate($credentials['username'] . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('username');
        }

        if (Auth::attempt($credentials)) {
            if (!Auth::user()->active) {
                Auth::logout();
                return back()->withErrors(['username' => 'This staff account is inactive. Contact an administrator.'])->onlyInput('username');
            }
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}