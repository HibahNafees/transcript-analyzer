<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Receives Stripe events and marks orders paid. Deliberately boring:
 *
 *  - Bad or missing signature, or a non-JSON body: 400, nothing touched.
 *  - Anything else that verifies: 200, even if we ignore it, so Stripe stops retrying.
 *  - paid_at is written with one conditional UPDATE (WHERE paid_at IS NULL), so a
 *    duplicate or retried event can never change it or double-count the order.
 */
class StripeWebhookController extends Controller
{
    /** Events that mean the money arrived. Checkout fires the first; the second covers delayed methods. */
    private const PAID_EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '') {
            // An empty secret would let anyone sign events with an empty HMAC key.
            Log::error('stripe.webhook.rejected', ['reason' => 'STRIPE_WEBHOOK_SECRET is not set']);

            return response('Webhook not configured', 400);
        }

        try {
            // Verifies the HMAC over the raw body and rejects timestamps older than 5 minutes (replays).
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('stripe.webhook.rejected', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response('Invalid signature', 400);
        }

        if (! in_array($event->type, self::PAID_EVENTS, true)) {
            return $this->ack($event->id, 'ignored type '.$event->type);
        }

        $session = $event->data->object;

        // Checkout completes before some payment methods settle; only count it once Stripe says paid.
        if (($session->payment_status ?? null) !== 'paid') {
            return $this->ack($event->id, 'not paid yet ('.($session->payment_status ?? 'none').')');
        }

        $orderId = $session->metadata->order_id ?? null;
        if (! is_string($orderId) || ! ctype_digit($orderId)) {
            return $this->ack($event->id, 'no order_id in metadata');
        }

        $updated = Order::whereKey((int) $orderId)
            ->whereNull('paid_at')
            ->update(['paid_at' => now(), 'stripe_event_id' => $event->id]);

        return $this->ack($event->id, match (true) {
            $updated === 1 => "order {$orderId} marked paid",
            Order::whereKey((int) $orderId)->exists() => "order {$orderId} already paid",
            default => "order {$orderId} not found",
        });
    }

    private function ack(string $eventId, string $outcome): Response
    {
        Log::info('stripe.webhook', ['event' => $eventId, 'outcome' => $outcome]);

        return response('ok', 200);
    }
}
