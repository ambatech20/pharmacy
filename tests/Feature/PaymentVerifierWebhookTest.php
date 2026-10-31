<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment-verifier webhook.
 *
 * This endpoint can mark a real payment as "verified", so it is the one place
 * in the system where an unauthenticated stranger would have power. Three
 * things have to hold, and each has its own test:
 *
 *   - it is off until somebody deliberately turns it on;
 *   - it refuses to run at all while enabled without a shared secret, rather
 *     than skipping the check (which is how an open endpoint appeared here);
 *   - with a secret, an unsigned or wrongly-signed body is rejected.
 */
class PaymentVerifierWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
        ]);
        $this->order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => Customer::create(['name' => 'Walk-in'])->id,
            'user_id' => $this->owner->id,
            'total_amount' => 100,
        ]);
    }

    protected function payment(string $reference = 'TX-1'): Payment
    {
        return Payment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'telebirr',
            'amount' => 100,
            'transaction_reference' => $reference,
            'verification_status' => 'pending',
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'event' => 'payment.updated',
            'data' => ['transaction_id' => 'TX-1', 'status' => 'verified'],
        ], $overrides);
    }

    /** Off by default: nothing should answer at all. */
    public function test_it_is_closed_until_the_integration_is_turned_on(): void
    {
        $payment = $this->payment();

        $this->postJson(route('api.payment-verifier.webhook'), $this->payload())
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->verification_status);
    }

    /**
     * The regression. This used to skip verification entirely when no secret
     * was configured, so "enabled" plus "no secret" was an open endpoint that
     * could mark any payment verified. It now refuses.
     */
    public function test_enabled_without_a_secret_refuses_instead_of_skipping_the_check(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', '');
        config(['services.payment_verifier.webhook_secret' => null]);

        $payment = $this->payment();

        $this->postJson(route('api.payment-verifier.webhook'), $this->payload())
            ->assertStatus(503);

        $this->assertSame('pending', $payment->fresh()->verification_status);
    }

    public function test_a_valid_signature_updates_the_payment(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payment = $this->payment();
        $payload = $this->payload();
        $signature = hash_hmac('sha256', json_encode($payload), 'whsec_abcdefghijklmnop');

        $this->postJson(route('api.payment-verifier.webhook'), $payload,
            ['X-Webhook-Signature' => $signature])->assertOk();

        $this->assertSame('verified', $payment->fresh()->verification_status);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payment = $this->payment();

        $this->postJson(route('api.payment-verifier.webhook'), $this->payload())
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->verification_status);
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payment = $this->payment();

        $this->postJson(route('api.payment-verifier.webhook'), $this->payload(),
            ['X-Webhook-Signature' => hash_hmac('sha256', 'the wrong body', 'guess')])
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->verification_status);
    }

    /** A correct signature over different bytes must not pass. */
    public function test_a_signature_for_a_different_body_is_rejected(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payment = $this->payment();
        $signature = hash_hmac('sha256', json_encode($this->payload([
            'data' => ['transaction_id' => 'TX-1', 'status' => 'failed'],
        ])), 'whsec_abcdefghijklmnop');

        $this->postJson(route('api.payment-verifier.webhook'), $this->payload(),
            ['X-Webhook-Signature' => $signature])
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->verification_status);
    }

    public function test_an_unknown_transaction_is_ignored_rather_than_failing(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payload = $this->payload(['data' => ['transaction_id' => 'NOPE', 'status' => 'verified']]);
        $signature = hash_hmac('sha256', json_encode($payload), 'whsec_abcdefghijklmnop');

        $this->postJson(route('api.payment-verifier.webhook'), $payload,
            ['X-Webhook-Signature' => $signature])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);
    }

    public function test_a_failure_status_marks_the_payment_declined(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $payment = $this->payment();
        $payload = $this->payload(['data' => ['transaction_id' => 'TX-1', 'status' => 'failed']]);
        $signature = hash_hmac('sha256', json_encode($payload), 'whsec_abcdefghijklmnop');

        $this->postJson(route('api.payment-verifier.webhook'), $payload,
            ['X-Webhook-Signature' => $signature])->assertOk();

        $this->assertSame('declined', $payment->fresh()->verification_status);
    }

    // ---- the form that turns it on -------------------------------------

    public function test_the_settings_form_refuses_to_enable_without_a_secret(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.integration.update'), [
                'payment_verifier_enabled' => '1',
                'payment_verifier_webhook_secret' => '',
            ])
            ->assertSessionHasErrors('payment_verifier_webhook_secret');

        $this->assertSame('0', Setting::value('payment_verifier_enabled', '0'));
    }

    public function test_the_settings_form_refuses_a_trivially_short_secret(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.integration.update'), [
                'payment_verifier_enabled' => '1',
                'payment_verifier_webhook_secret' => 'short',
            ])
            ->assertSessionHasErrors('payment_verifier_webhook_secret');
    }

    public function test_the_settings_form_accepts_a_real_secret(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.integration.update'), [
                'payment_verifier_enabled' => '1',
                'payment_verifier_webhook_secret' => 'whsec_abcdefghijklmnop',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('1', Setting::value('payment_verifier_enabled'));
        $this->assertSame('whsec_abcdefghijklmnop', Setting::value('payment_verifier_webhook_secret'));
    }

    /** Switching it off again needs no secret at all. */
    public function test_the_settings_form_can_turn_the_integration_off(): void
    {
        Setting::put('payment_verifier_enabled', '1');
        Setting::put('payment_verifier_webhook_secret', 'whsec_abcdefghijklmnop');

        $this->actingAs($this->owner)
            ->put(route('settings.integration.update'), [
                'payment_verifier_enabled' => '0',
                'payment_verifier_webhook_secret' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('0', Setting::value('payment_verifier_enabled'));
    }
}
