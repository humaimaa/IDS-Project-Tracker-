<?php

namespace App\Http\Controllers;

use App\EmailChangeMailer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailChangeController extends Controller
{
    private function pendingUser(Request $request, string $user, string $token, bool $lock = false): User
    {
        abort_unless((string) $request->user()->id === $user, 403);
        $account = User::whereKey($user)->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
        abort_if(config('portfolio.enabled') && $account->account_status !== 'Active', 403);
        abort_unless($account->pending_email && $account->pending_email_token_hash && $account->pending_email_expires_at?->isFuture() && hash_equals($account->pending_email_token_hash, hash('sha256', $token)), 403, 'This verification link is no longer valid. Request a new one from your account page.');

        return $account;
    }

    public function show(Request $request, string $user, string $token): View
    {
        return view('users.verify-email-change', ['user' => $this->pendingUser($request, $user, $token), 'confirmationUrl' => $request->fullUrl()]);
    }

    public function confirm(Request $request, string $user, string $token): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $user, $token): void {
                $account = $this->pendingUser($request, $user, $token, true);
                if (User::whereKeyNot($account->id)->whereRaw('LOWER(email) = ?', [strtolower($account->pending_email)])->exists()) {
                    throw ValidationException::withMessages(['email' => 'This email is now used by another account. Choose a different address.']);
                }
                $account->forceFill(['email' => $account->pending_email, 'email_verified_at' => now(), 'pending_email' => null, 'pending_email_token_hash' => null, 'pending_email_expires_at' => null, 'remember_token' => Str::random(60)])->save();
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'])) {
                throw ValidationException::withMessages(['email' => 'This email is now used by another account. Choose a different address.']);
            }
            throw $exception;
        }
        $request->session()->regenerate();

        return to_route('account.edit')->with('success', 'Email verified and updated. Use your new email the next time you log in.');
    }

    public function resend(Request $request, EmailChangeMailer $mailer): RedirectResponse
    {
        $account = $request->user()->fresh();
        abort_if(config('portfolio.enabled') && $account->account_status !== 'Active', 403);
        abort_unless($account->pending_email, 422);
        $mailer->requestChange($account, $account->pending_email);

        return to_route('account.edit')->with('success', 'A new verification email has been sent. Previous links are no longer valid.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail()->forceFill(['pending_email' => null, 'pending_email_token_hash' => null, 'pending_email_expires_at' => null])->save();
        });

        return to_route('account.edit')->with('success', 'Email change cancelled. Your current email is unchanged.');
    }
}
