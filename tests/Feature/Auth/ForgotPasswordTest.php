<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FR-013 — the password-reset endpoint must reveal nothing about whether an
 * address has an account.
 *
 * It used to carry `exists:users,email`, so a registered address answered 200
 * and an unregistered one answered 422 naming the field. That is an
 * account-enumeration oracle: anyone could test an address list against it.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_and_unknown_addresses_produce_identical_responses(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'registered@example.com']);

        $known   = $this->postJson('/api/auth/forgot-password', ['email' => 'registered@example.com']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();

        // SC-005 asks for byte-identical, so compare the raw bodies rather than
        // the decoded arrays.
        $this->assertSame(
            $known->getContent(),
            $unknown->getContent(),
            'The response must not reveal whether the address is registered.'
        );
    }

    /**
     * The second request is where the naive fix leaks: a registered address
     * hits the resend cooldown and answers "please wait", while an unregistered
     * one answers 200.
     */
    public function test_a_repeated_request_also_produces_identical_responses(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'registered@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'registered@example.com'])->assertOk();

        $known   = $this->postJson('/api/auth/forgot-password', ['email' => 'registered@example.com']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($known->getContent(), $unknown->getContent());
        $this->assertSame($known->status(), $unknown->status());
    }

    /**
     * The endpoint must not be usable to mail arbitrary inboxes.
     *
     * EmailOtpMail implements ShouldQueue, so it is *queued* rather than sent —
     * assertNothingSent() would pass here whether or not the bug existed.
     */
    public function test_no_mail_is_queued_for_an_address_with_no_account(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Mail::assertNotQueued(EmailOtpMail::class);
    }

    public function test_mail_is_still_queued_for_a_registered_address(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'registered@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'registered@example.com'])->assertOk();

        Mail::assertQueued(EmailOtpMail::class);
    }

    public function test_a_malformed_address_is_still_rejected(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422);
    }
}
