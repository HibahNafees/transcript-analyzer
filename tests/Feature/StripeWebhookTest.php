<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    private function order(): Order
    {
        return User::factory()->create()->orders()->create(['analysis_credits' => 50, 'amount_cents' => 500]);
    }

    private function event(array $metadata, string $id = 'evt_test_1', string $type = 'checkout.session.completed', string $paymentStatus = 'paid'): string
    {
        return json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => [
                'id' => 'cs_test_1',
                'object' => 'checkout.session',
                'payment_status' => $paymentStatus,
                'metadata' => $metadata,
            ]],
        ]);
    }

    /** Signs the body the way Stripe does: v1 = HMAC-SHA256("{t}.{body}", secret). */
    private function send(string $payload, ?string $secret = self::SECRET, ?int $timestamp = null): TestResponse
    {
        $t = $timestamp ?? time();
        $signature = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret ?? '');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }

    public function test_paid_checkout_sets_paid_at(): void
    {
        $order = $this->order();

        $this->send($this->event(['order_id' => (string) $order->id]))->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('evt_test_1', $order->fresh()->stripe_event_id);
    }

    public function test_same_event_twice_sets_paid_at_once(): void
    {
        $order = $this->order();
        $payload = $this->event(['order_id' => (string) $order->id]);

        Carbon::setTestNow('2026-10-18 10:00:00');
        $this->send($payload)->assertOk();

        Carbon::setTestNow('2026-10-18 10:05:00');
        $this->send($payload)->assertOk();

        $this->assertSame('2026-10-18 10:00:00', $order->fresh()->paid_at->toDateTimeString());
    }

    public function test_bad_signature_is_rejected_and_changes_nothing(): void
    {
        $order = $this->order();

        $this->send($this->event(['order_id' => (string) $order->id]), secret: 'whsec_wrong')
            ->assertStatus(400);

        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_missing_signature_and_stale_replay_are_rejected(): void
    {
        $order = $this->order();
        $payload = $this->event(['order_id' => (string) $order->id]);

        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->send($payload, timestamp: time() - 600)->assertStatus(400);

        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_empty_secret_rejects_events_signed_with_an_empty_key(): void
    {
        config(['services.stripe.webhook_secret' => '']);
        $order = $this->order();

        $this->send($this->event(['order_id' => (string) $order->id]), secret: '')->assertStatus(400);

        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_verified_events_we_do_not_act_on_still_return_200(): void
    {
        $order = $this->order();
        $id = (string) $order->id;

        $this->send($this->event(['order_id' => $id], 'evt_a', 'payment_intent.created'))->assertOk();
        $this->send($this->event(['order_id' => $id], 'evt_b', paymentStatus: 'unpaid'))->assertOk();
        $this->send($this->event([], 'evt_c'))->assertOk();
        $this->send($this->event(['order_id' => '999999'], 'evt_d'))->assertOk();

        $this->assertNull($order->fresh()->paid_at);
    }
}
