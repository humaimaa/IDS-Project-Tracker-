<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\SetupAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view(User::exists() ? 'users.login' : 'users.setup');
    }

    public function setup(SetupAccountRequest $request): RedirectResponse
    {
        return Cache::lock('first-account-setup', 10)->block(5, function () use ($request): RedirectResponse {
            abort_if(User::exists(), 403);
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            Auth::login($user);
            $request->session()->regenerate();

            return to_route('account.edit')->with('success', 'Your account is ready.');
        });
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
