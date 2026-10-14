<!doctype html>
<html lang="en"><body style="margin:0;background:#f0fdfa;font-family:Arial,sans-serif;color:#12333b;padding:32px">
<div style="max-width:560px;margin:auto;background:white;border-radius:16px;padding:32px">
    <p style="font-weight:bold;color:#00858a">IDS · Project Tracker</p>
    <h1 style="font-size:24px">Verify your new email</h1>
    <p>Hello {{ $user->name }},</p>
    <p>You requested to change your account email to <strong>{{ $email }}</strong>.</p>
    <p style="margin:28px 0"><a href="{{ $url }}" style="display:inline-block;background:#00858a;color:white;padding:14px 22px;border-radius:8px;text-decoration:none">Verify new email</a></p>
    <p>This link expires in one hour and can only be used once. Sign in with your current account to confirm the change.</p>
    <p>Your current email stays active until you confirm. If you did not request this change, ignore this email.</p>
</div></body></html>
