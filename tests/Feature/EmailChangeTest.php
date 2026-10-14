<?php

namespace Tests\Feature;

use App\EmailChangeMailer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPMailer\PHPMailer\PHPMailer;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function requestChange(User $user, string $email = 'new@example.test'): string
    {
        $url = '';
        $this->partialMock(EmailChangeMailer::class)->shouldReceive('send')->once()->andReturnUsing(function (User $recipient, string $address, string $link) use (&$url, $email, $user): void {
            $this->assertSame($user->id, $recipient->id);
            $this->assertSame($email, $address);
            $url = $link;
        });
        $this->actingAs($user)->put('/account', ['name' => $user->name, 'email' => $email, 'current_password' => 'password'])->assertRedirect(route('account.edit'))->assertSessionHasNoErrors();

        return $url;
    }

    public function test_new_email_replaces_login_only_after_explicit_single_use_verification(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $url = $this->requestChange($user);
        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertSame('new@example.test', $user->fresh()->pending_email);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $token), $user->fresh()->pending_email_token_hash);
        $this->get($url)->assertOk()->assertSee('Verify and change email')->assertDontSee('id="sidebar"', false);
        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->post($url)->assertRedirect(route('account.edit'));
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->pending_email_token_hash);
        $this->post($url)->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $originalEmail, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'new@example.test', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_wrong_user_tampered_and_expired_links_cannot_verify(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $url = $this->requestChange($user);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($user)->post($url.'&changed=1')->assertForbidden();
        $this->travel(61)->minutes();
        $this->post($url)->assertForbidden();
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_resending_and_cancelling_invalidate_previous_links(): void
    {
        $user = User::factory()->create();
        $oldUrl = $this->requestChange($user);
        $newUrl = '';
        $this->partialMock(EmailChangeMailer::class)->shouldReceive('send')->once()->andReturnUsing(function (User $recipient, string $email, string $url) use (&$newUrl): void {
            $newUrl = $url;
        });
        $this->post('/account/email/resend')->assertRedirect(route('account.edit'));
        $this->post($oldUrl)->assertForbidden();
        $this->get($newUrl)->assertOk();
        $this->delete('/account/email')->assertRedirect(route('account.edit'));
        $this->post($newUrl)->assertForbidden();
        $this->assertNull($user->fresh()->pending_email);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_smtp_failure_does_not_change_account_or_leave_a_new_pending_address(): void
    {
        $user = User::factory()->create();
        $this->partialMock(EmailChangeMailer::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->actingAs($user)->put('/account', ['name' => 'Changed name', 'email' => 'new@example.test', 'current_password' => 'password'])->assertSessionHasErrors('email');
        $this->assertSame($user->getRawOriginal('email'), $user->fresh()->email);
        $this->assertNull($user->fresh()->pending_email);
        $this->assertNotSame('Changed name', $user->fresh()->name);
    }

    public function test_address_taken_after_request_cannot_be_claimed(): void
    {
        $user = User::factory()->create();
        $url = $this->requestChange($user);
        User::factory()->create(['email' => 'new@example.test']);
        $this->post($url)->assertSessionHasErrors('email');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_phpmailer_uses_existing_smtp_settings_and_escapes_email_content(): void
    {
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.username' => 'test-login', 'mail.mailers.smtp.password' => 'test-secret', 'mail.mailers.smtp.encryption' => 'tls', 'mail.mailers.smtp.url' => null, 'mail.from.address' => 'tracker@example.test', 'mail.from.name' => 'IDS Tracker']);
        $mail = \Mockery::mock(PHPMailer::class)->makePartial();
        $mail->shouldReceive('send')->once()->andReturn(true);
        $this->app->instance(PHPMailer::class, $mail);
        $user = User::factory()->make(['name' => '<script>alert(1)</script>']);
        (new EmailChangeMailer)->send($user, 'new@example.test', 'https://example.test/verify?signature=test');
        $this->assertSame('smtp', $mail->Mailer);
        $this->assertSame('smtp.example.test', $mail->Host);
        $this->assertSame(587, $mail->Port);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('tls', $mail->SMTPSecure);
        $this->assertStringContainsString('&lt;script&gt;', $mail->Body);
        $this->assertStringNotContainsString('<script>', $mail->Body);
        $this->assertStringContainsString('https://example.test/verify?signature=test', $mail->AltBody);
        $this->assertSame('new@example.test', $mail->getToAddresses()[0][0]);
    }
}
