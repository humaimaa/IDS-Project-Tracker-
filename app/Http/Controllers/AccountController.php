<?php

namespace App\Http\Controllers;

use App\EmailChangeMailer;
use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('users.account', ['user' => $request->user()]);
    }

    public function update(UpdateAccountRequest $request, EmailChangeMailer $mailer): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $request->validated('email') !== $user->email;
        DB::transaction(function () use ($request, $user, $emailChanged, $mailer): void {
            $user->fill($request->safe()->only(['name']));
            if ($request->filled('password')) {
                $user->password = $request->validated('password');
                $user->remember_token = Str::random(60);
            }
            $user->save();
            if ($emailChanged) {
                $mailer->requestChange($user, $request->validated('email'));
            }
        });
        $request->session()->regenerate();

        return to_route('account.edit')->with('success', $emailChanged ? 'Verification email sent to your new address. Your current email remains active until you confirm.' : 'Account updated.');
    }
}
