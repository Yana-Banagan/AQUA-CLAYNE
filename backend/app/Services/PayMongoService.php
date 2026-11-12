<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PayMongoService
{
    public function createCheckout(Order $order, Payment $payment, ?string $provider = null): array
    {
        $secret = config('services.paymongo.secret_key');
        if (blank($secret)) {
            throw ValidationException::withMessages(['payment' => ['Online payment is not configured yet.']]);
        }

        $order->loadMissing('items.product', 'customer.user');
        $amount = (int) round(((float) $order->total_amount) * 100);
        if ($amount < 100) {
            throw ValidationException::withMessages(['payment' => ['This order does not have a payable amount.']]);
        }
        if ((int) round(((float) $payment->amount) * 100) !== $amount) {
            throw ValidationException::withMessages(['payment' => ['The payment record does not match the current order total. Please contact Aqua Clayne support.']]);
        }

        $configuredTypes = (array) config('services.paymongo.payment_method_types', ['gcash', 'qrph']);
        $provider = strtolower((string) $provider);
        if ($provider !== '' && (!in_array($provider, ['gcash', 'qrph', 'card'], true) || !in_array($provider, $configuredTypes, true))) {
            throw ValidationException::withMessages(['payment_method_type' => ['The selected online payment method is unavailable.']]);
        }
        $paymentTypes = $provider !== '' ? [$provider] : $configuredTypes;

        $lineItems = $order->items->map(fn ($item) => [
            'amount' => (int) round(((float) $item->unit_price) * 100),
            'currency' => 'PHP',
            'name' => $item->product?->name ?? 'Water refill service',
            'quantity' => $item->quantity,
        ])->values()->all();
        $itemTotal = array_sum(array_map(fn ($item) => $item['amount'] * $item['quantity'], $lineItems));
        if ($itemTotal < $amount) {
            $lineItems[] = [
                'amount' => $amount - $itemTotal,
                'currency' => 'PHP',
                'name' => 'Approved order fees',
                'quantity' => 1,
            ];
        }

        $apiUrl = $this->apiBaseUrl();

        // A signed cancellation return may update only this pending payment.
        // The signature prevents arbitrary browser requests from changing a
        // payment status, and the order itself remains pending and retryable.
        $cancelParams = [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'signature' => hash_hmac(
                'sha256',
                $order->id . ':' . $payment->id,
                (string) config('app.key')
            ),
        ];
        $cancelBase = (string) config('services.paymongo.cancel_url');
        if (blank($cancelBase)) {
            $cancelBase = route('payment.paymongo.cancel');
        }
        $cancelUrl = $cancelBase
            . (str_contains($cancelBase, '?') ? '&' : '?')
            . http_build_query($cancelParams);

        // Reuse one key while a checkout attempt is pending (including when
        // the customer reopens it). Rotate the key only after a failed or
        // cancelled attempt so a retry can create a fresh hosted session.
        $idempotencyKey = DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (
                blank($lockedPayment->gateway_checkout_idempotency_key) ||
                in_array($lockedPayment->status, ['failed', 'cancelled'], true)
            ) {
                $lockedPayment->gateway_checkout_idempotency_key =
                    'checkout-' . $lockedPayment->id . '-' . Str::ulid();
                $lockedPayment->status = 'pending';
                $lockedPayment->failure_reason = null;
                $lockedPayment->save();
            }

            return $lockedPayment->gateway_checkout_idempotency_key;
        });

        $successBase = (string) config('services.paymongo.success_url');
        if (blank($successBase)) {
            $successBase = route('payment.paymongo.success');
        }
        if (request()?->hasHeader('Host')) {
            $reqHost = request()->getSchemeAndHttpHost();
            if (str_contains($successBase, '127.0.0.1:8000') && !str_contains($reqHost, '127.0.0.1')) {
                $successBase = str_replace('http://127.0.0.1:8000', $reqHost, $successBase);
            }
        }
        $successUrl = $successBase
            . (str_contains($successBase, '?') ? '&' : '?')
            . http_build_query([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);

        $response = Http::acceptJson()->withBasicAuth($secret, '')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($apiUrl . '/checkout_sessions', [
                'data' => ['attributes' => [
                    'line_items' => $lineItems,
                    'payment_method_types' => $paymentTypes,
                    'reference_number' => $payment->gateway_reference,
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'description' => "AQUA CLAYNE refill order {$order->order_number}",
                    'metadata' => ['order_id' => $order->id, 'payment_id' => $payment->id],
                ]],
            ]);

        if (!$response->successful()) {
            report(new \RuntimeException('PayMongo checkout creation failed: ' . $response->body()));
            throw ValidationException::withMessages(['payment' => ['Unable to create the secure checkout session. Please try again.']]);
        }

        $attributes = $response->json('data.attributes', []);
        $sessionId = $response->json('data.id');
        $checkoutUrl = $attributes['checkout_url'] ?? null;
        if (blank($sessionId) || blank($checkoutUrl)) {
            throw ValidationException::withMessages(['payment' => ['PayMongo returned an invalid checkout session.']]);
        }

        $payment->update([
            'gateway_checkout_session_id' => $sessionId,
            'gateway_payment_method' => $provider !== '' ? $provider : $payment->gateway_payment_method,
            'failure_reason' => null,
            'status' => 'pending',
        ]);

        return ['checkout_url' => $checkoutUrl, 'checkout_session_id' => $sessionId, 'payment_id' => $payment->id];
    }

    /**
     * Retrieve an existing PayMongo Hosted Checkout Session by its session ID.
     */
    public function retrieveCheckout(string $checkoutSessionId): ?array
    {
        $secret = config('services.paymongo.secret_key');
        if (blank($secret) || blank($checkoutSessionId)) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withBasicAuth($secret, '')
                ->timeout(10)
                ->get($this->apiBaseUrl() . '/checkout_sessions/' . rawurlencode($checkoutSessionId));

            if ($response->successful()) {
                return $response->json();
            }

            \Illuminate\Support\Facades\Log::warning('PayMongo retrieve checkout returned an unsuccessful response.', [
                'session_id' => $checkoutSessionId,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PayMongo retrieve checkout failed: ' . $e->getMessage(), [
                'session_id' => $checkoutSessionId,
            ]);
        }

        return null;
    }

    private function apiBaseUrl(): string
    {
        $apiUrl = rtrim((string) (config('services.paymongo.api_url') ?: 'https://api.paymongo.com/v2'), '/');

        return $apiUrl !== '' ? $apiUrl : 'https://api.paymongo.com/v2';
    }
}
