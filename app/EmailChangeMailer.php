<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class EmailChangeMailer
{
    public function requestChange(User $user, string $email): void
    {
        DB::transaction(function () use ($user, $email): void {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $token = Str::random(64);
            $expires = now()->addHour();
            $lockedUser->forceFill(['pending_email' => $email, 'pending_email_token_hash' => hash('sha256', $token), 'pending_email_expires_at' => $expires])->save();
            $url = URL::temporarySignedRoute('account.email.verify', $expires, ['user' => $user->id, 'token' => $token]);
            try {
                $this->send($lockedUser, $email, $url);
            } catch (\Throwable $exception) {
                Log::warning('Email change verification could not be sent.', ['user_id' => $user->id, 'exception_type' => $exception::class]);
                throw ValidationException::withMessages(['email' => 'Could not send the verification email. Check the SMTP settings and try again. Your current email is unchanged.']);
            }
        });
    }

    public function send(User $user, string $email, string $url): void
    {
        $smtp = config('mail.mailers.smtp');
        if (! empty($smtp['url'])) {
            $parts = parse_url($smtp['url']);
            $smtp = array_replace($smtp, ['host' => $parts['host'] ?? '', 'port' => $parts['port'] ?? 587, 'username' => isset($parts['user']) ? rawurldecode($parts['user']) : null, 'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null, 'scheme' => $parts['scheme'] ?? 'smtp']);
        }
        $mail = app(PHPMailer::class);
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = (int) $smtp['port'];
        $mail->SMTPAuth = ! empty($smtp['username']);
        $mail->Username = (string) ($smtp['username'] ?? '');
        $mail->Password = (string) ($smtp['password'] ?? '');
        $mail->SMTPSecure = ($smtp['scheme'] ?? '') === 'smtps' || ($smtp['encryption'] ?? '') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : (($smtp['encryption'] ?? '') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        if (! $mail->setFrom(config('mail.from.address'), config('mail.from.name')) || ! $mail->addAddress($email, $user->name)) {
            throw new RuntimeException('The verification email address is invalid.');
        }
        $mail->isHTML(true);
        $mail->Subject = 'Verify your new IDS Project Tracker email';
        $mail->Body = view('emails.email-change', compact('user', 'email', 'url'))->render();
        $mail->AltBody = "Hello {$user->name},\n\nConfirm your new IDS Project Tracker email: {$email}\n\n{$url}\n\nThis link expires in one hour. Your current email stays active until you confirm. If you did not request this change, ignore this email.";
        if (! $mail->send()) {
            throw new RuntimeException('SMTP did not accept the verification email.');
        }
    }
}
