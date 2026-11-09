<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\OrderWorkflowService;
use App\Services\PayMongoService;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayMongoController extends Controller
{
    /** Record a signed customer cancellation without changing order status. */
    public function cancelReturn(Request $request): JsonResponse
    {
        $orderId = (string) $request->query('order_id', '');
        $paymentId = (string) $request->query('payment_id', '');
        $signature = (string) $request->query('signature', '');
        $expected = hash_hmac(
            'sha256',
            $orderId . ':' . $paymentId,
            (string) config('app.key')
        );

        if (!$orderId || !$paymentId || !$signature || !hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid payment return.'], 403);
        }

        $cancelled = false;
        DB::transaction(function () use ($paymentId, $orderId, &$cancelled) {
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->where('order_id', $orderId)
                ->where('method', 'online')
                ->lockForUpdate()
                ->first();

            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'status' => 'cancelled',
                    'failure_reason' => 'Customer cancelled PayMongo checkout.',
                ]);
                $cancelled = true;
            }
        });

        if ($cancelled) {
            app(RealtimeEventService::class)->emit(
                'payment', $paymentId, 'payment_status_changed', 'cancelled'
            );
            app(RealtimeEventService::class)->emit(
                'order', $orderId, 'payment_status_changed', 'cancelled'
            );
        }

        return response()->json([
            'message' => 'Payment was cancelled. Your order has not been marked as paid.',
        ]);
    }

    /**
     * Create a PayMongo Hosted Checkout Session.
     *
     * POST /api/orders/{order}/paymongo/checkout
     */
    public function checkout(
        Request $request,
        Order $order,
        PayMongoService $payMongo
    ): JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Verify that the authenticated customer owns the order
        |--------------------------------------------------------------------------
        */
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $customer = $user->customer;

        if (!$customer || (string) $order->customer_id !== (string) $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to pay for this order.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent payment for cancelled/rejected orders
        |--------------------------------------------------------------------------
        */
        if (in_array($order->status, ['cancelled', 'canceled', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This order can no longer be paid.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        /*
        |--------------------------------------------------------------------------
        | Find or create the online payment record
        |--------------------------------------------------------------------------
        */
        $payment = Payment::where('order_id', $order->id)->first();

        if (!$payment) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => 'online',
                'amount' => $order->total_amount,
                'status' => 'pending',
                'gateway' => 'paymongo',
                'gateway_reference' => "AC-ORDER-{$order->id}",
            ]);
            $order->update(['payment_method' => 'online']);
        } elseif ($payment->method !== 'online' && $payment->status === 'pending') {
            $payment->update([
                'method' => 'online',
                'gateway' => 'paymongo',
                'gateway_reference' => "AC-ORDER-{$order->id}",
            ]);
            $order->update(['payment_method' => 'online']);
        }

        /*
        |--------------------------------------------------------------------------
        | Already paid?
        |--------------------------------------------------------------------------
        */
        if ($payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This order has already been paid.',
                'payment_status' => 'paid',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Create PayMongo Checkout Session
        |--------------------------------------------------------------------------
        */
        try {
            $checkout = $payMongo->createCheckout($order, $payment);

            return response()->json([
                'success' => true,
                'message' => 'PayMongo checkout session created.',
                'checkout_url' => $checkout['checkout_url'] ?? null,
                'data' => $checkout,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('PayMongo checkout creation failed.', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the PayMongo checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return current payment status and automatically sync with PayMongo if pending.
     *
     * GET /api/orders/{order}/payment-status
     */
    public function paymentStatus(
        Request $request,
        Order $order,
        PayMongoService $payMongo
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $customer = $user->customer;

        if (!$customer || (string) $order->customer_id !== (string) $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this payment.',
            ], 403);
        }

        $payment = Payment::where('order_id', $order->id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found.',
            ], 404);
        }

        // If online payment is pending with a session ID, query PayMongo API to see if customer completed it
        if ($payment->method === 'online' && $payment->status === 'pending' && !empty($payment->gateway_checkout_session_id)) {
            $payment = $this->syncPaymentWithGateway($payment, $order, $payMongo);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'payment_method' => $payment->method,
                'amount' => (float) ($payment->amount ?? 0),
                'status' => $payment->status,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'gateway_checkout_session_id' => $payment->gateway_checkout_session_id,
                'gateway_reference' => $payment->gateway_reference,
                'paid_at' => $payment->paid_at,
                'failure_reason' => $payment->failure_reason,
            ],
        ]);
    }

    /**
     * PayMongo webhook.
     *
     * POST /api/paymongo/webhook
     *
     * IMPORTANT:
     * The webhook is the source of truth for successful payments.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        /*
        |--------------------------------------------------------------------------
        | Verify PayMongo signature using RAW request body
        |--------------------------------------------------------------------------
        */
        if (!$this->hasValidSignature($request, $payload)) {
            Log::warning('Invalid PayMongo webhook signature.', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Decode JSON
        |--------------------------------------------------------------------------
        */
        $event = json_decode($payload, true);

        if (!is_array($event)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Current PayMongo event structure
        |--------------------------------------------------------------------------
        */
        $eventData = $event['data'] ?? [];
        $eventAttributes = $eventData['attributes'] ?? [];

        // PayMongo v2 sends `data.type` and `data.data`; the older v1
        // envelope nests these values under `data.attributes`.
        $eventId = $event['id'] ?? $eventData['id'] ?? null;
        $eventType = $eventData['type']
            ?? $eventAttributes['type']
            ?? null;
        $livemode = (bool) (
            $eventData['livemode']
            ?? $eventAttributes['livemode']
            ?? false
        );
        $resource = $eventData['data']
            ?? $eventAttributes['data']
            ?? [];

        $resourceId = $resource['id'] ?? null;

        $resourceType = $resource['type'] ?? null;

        $attributes = $resource['attributes'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Ignore unsupported events
        |--------------------------------------------------------------------------
        */
        $supportedEvents = [
            'checkout_session.payment.paid',
            'payment.paid',
            'payment.failed',
        ];

        if (!in_array($eventType, $supportedEvents, true)) {
            Log::info('Ignoring unsupported PayMongo webhook event.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            // Return 200 so PayMongo does not keep retrying an event
            // that this application intentionally does not process.
            return response()->json([
                'success' => true,
                'message' => 'Event received but not processed.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Checkout Session information
        |--------------------------------------------------------------------------
        */
        $checkoutSessionId = null;
        $referenceNumber = null;
        $metadata = [];

        $paymentId = null;
        $paymentIntentId = null;

        $paymentAttributes = [];

        /*
        |--------------------------------------------------------------------------
        | checkout_session.payment.paid
        |--------------------------------------------------------------------------
        */
        if ($eventType === 'checkout_session.payment.paid') {
            $checkoutSessionId = $resourceId;

            $referenceNumber = $attributes['reference_number'] ?? null;

            $metadata = $attributes['metadata'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Get the resulting PayMongo payment
            |--------------------------------------------------------------------------
            */
            $payments = $attributes['payments'] ?? [];

            if (!empty($payments) && isset($payments[0])) {
                $paymentResource = $payments[0];

                $paymentId = $paymentResource['id'] ?? null;

                $paymentAttributes = $paymentResource['attributes'] ?? [];

                $paymentIntentId =
                    $paymentAttributes['payment_intent_id']
                    ?? ($attributes['payment_intent']['id'] ?? null);
            } else {
                /*
                |--------------------------------------------------------------------------
                | Fallback to Payment Intent
                |--------------------------------------------------------------------------
                */
                $paymentIntentId = $attributes['payment_intent']['id'] ?? null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | payment.paid / payment.failed
        |--------------------------------------------------------------------------
        */
        if (
            $eventType === 'payment.paid' ||
            $eventType === 'payment.failed'
        ) {
            $paymentId = $resourceId;

            $paymentAttributes = $attributes;

            $paymentIntentId =
                $attributes['payment_intent_id']
                ?? null;

            $metadata = $attributes['metadata'] ?? [];

            $referenceNumber =
                $attributes['external_reference_number']
                ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Try to identify our internal Payment record
        |--------------------------------------------------------------------------
        */
        $payment = null;

        /*
        |--------------------------------------------------------------------------
        | 1. Metadata payment_id
        |--------------------------------------------------------------------------
        */
        if (!empty($metadata['payment_id'])) {
            $payment = Payment::find($metadata['payment_id']);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Metadata order_id
        |--------------------------------------------------------------------------
        */
        if (!$payment && !empty($metadata['order_id'])) {
            $payment = Payment::where('order_id', $metadata['order_id'])
                ->where('method', 'online')
                ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | 3. PayMongo payment ID
        |--------------------------------------------------------------------------
        */
        if (!$payment && !empty($paymentId)) {
            $payment = Payment::where(
                'gateway_payment_id',
                $paymentId
            )->first();
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Checkout Session ID
        |--------------------------------------------------------------------------
        */
        if (!$payment && !empty($checkoutSessionId)) {
            $payment = Payment::where(
                'gateway_checkout_session_id',
                $checkoutSessionId
            )->first();
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Reference number
        |--------------------------------------------------------------------------
        */
        if (!$payment && !empty($referenceNumber)) {
            $payment = Payment::where(
                'gateway_reference',
                $referenceNumber
            )->first();
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Order number
        |--------------------------------------------------------------------------
        */
        if (!$payment && !empty($referenceNumber)) {
            $order = Order::where(
                'order_number',
                $referenceNumber
            )->first();

            if ($order) {
                $payment = Payment::where('order_id', $order->id)
                    ->where('method', 'online')
                    ->first();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Payment not found
        |--------------------------------------------------------------------------
        */
        if (!$payment) {
            Log::error('PayMongo webhook could not match payment.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payment_id' => $paymentId,
                'checkout_session_id' => $checkoutSessionId,
                'reference_number' => $referenceNumber,
                'metadata' => $metadata,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Return 200 instead of 500
            |
            | This prevents endless PayMongo retries for a webhook that
            | arrived before our local record was available.
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Webhook received but no matching payment was found.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Update payment safely inside a transaction
        |--------------------------------------------------------------------------
        */
        $paymentBecamePaid = false;
        $paymentBecameFailed = false;

        try {
            DB::transaction(function () use ($payment, $eventType, $paymentId, $paymentIntentId, $checkoutSessionId, $referenceNumber, $paymentAttributes, $eventId, $livemode, &$paymentBecamePaid, &$paymentBecameFailed) {
                /*
                |--------------------------------------------------------------------------
                | Lock the payment row
                |--------------------------------------------------------------------------
                */
                $lockedPayment = Payment::whereKey($payment->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedPayment) {
                    throw new \RuntimeException(
                        'Payment disappeared while processing PayMongo webhook.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Save PayMongo identifiers
                |--------------------------------------------------------------------------
                */
                if (!empty($paymentId)) {
                    $lockedPayment->gateway_payment_id = $paymentId;
                }

                if (!empty($paymentIntentId)) {
                    $lockedPayment->gateway_payment_intent_id =
                        $paymentIntentId;
                }

                if (!empty($checkoutSessionId)) {
                    $lockedPayment->gateway_checkout_session_id =
                        $checkoutSessionId;
                }

                if (!empty($referenceNumber)) {
                    $lockedPayment->gateway_reference =
                        $referenceNumber;
                }

                /*
                |--------------------------------------------------------------------------
                | Successful payment
                |--------------------------------------------------------------------------
                */
                if (
                    $eventType === 'checkout_session.payment.paid' ||
                    $eventType === 'payment.paid'
                ) {
                    $paymentBecamePaid = $lockedPayment->status !== 'paid';
                    $lockedPayment->status = 'paid';

                    /*
                    |--------------------------------------------------------------------------
                    | FIX FOR YOUR SCREENSHOT
                    |
                    | PayMongo amounts are integers in the smallest currency unit.
                    | Your local payment amount may also be null/string/decimal.
                    | We safely convert it before formatting or arithmetic.
                    |--------------------------------------------------------------------------
                    */
                    $amount = (float) ($lockedPayment->amount ?? 0);

                    /*
                    |--------------------------------------------------------------------------
                    | Save PayMongo fee and net amount when supplied
                    |--------------------------------------------------------------------------
                    */
                    if (array_key_exists('fee', $paymentAttributes)) {
                        $lockedPayment->gateway_fee =
                            isset($paymentAttributes['fee'])
                            ? ((float) $paymentAttributes['fee'] / 100)
                            : null;
                    }

                    if (array_key_exists('net_amount', $paymentAttributes)) {
                        $lockedPayment->gateway_net_amount =
                            isset($paymentAttributes['net_amount'])
                            ? ((float) $paymentAttributes['net_amount'] / 100)
                            : null;
                    }

                    $lockedPayment->paid_at ??= now();

                    $lockedPayment->failure_reason = null;

                    $lockedPayment->save();

                    /*
                    |--------------------------------------------------------------------------
                    | Load order
                    |--------------------------------------------------------------------------
                    */
                    $order = Order::find($lockedPayment->order_id);

                    if ($order && $order->status === 'pending') {
                        app(OrderWorkflowService::class)->transition(
                            $order,
                            'confirmed'
                        );
                    }

                    if ($order) {
                        /*
                        |--------------------------------------------------------------------------
                        | Audit log
                        |--------------------------------------------------------------------------
                        */
                        if ($paymentBecamePaid) {
                            try {
                                AuditLog::create([
                                'user_id' => null,
                                'action' => 'payment.paid',
                                'entity_type' => 'payment',
                                'entity_id' => $lockedPayment->id,
                                'description' =>
                                    'PayMongo payment confirmed for order #'
                                    . $order->order_number
                                    . ' - Amount: ₱'
                                    . number_format($amount, 2),
                                'metadata' => [
                                    'gateway' => 'paymongo',
                                    'gateway_payment_id' =>
                                        $lockedPayment->gateway_payment_id,
                                    'gateway_checkout_session_id' =>
                                        $lockedPayment->gateway_checkout_session_id,
                                    'gateway_reference' =>
                                        $lockedPayment->gateway_reference,
                                    'payment_intent_id' =>
                                        $lockedPayment->gateway_payment_intent_id,
                                    'event_id' => $eventId,
                                    'livemode' => $livemode,
                                ],
                                ]);
                            } catch (\Throwable $e) {
                            /*
                            |--------------------------------------------------------------------------
                            | Do not make a successful payment fail only
                            | because audit logging has a separate issue.
                            |--------------------------------------------------------------------------
                            */
                                Log::warning(
                                    'PayMongo payment audit log failed.',
                                    [
                                        'payment_id' => $lockedPayment->id,
                                        'error' => $e->getMessage(),
                                    ]
                                );
                            }
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Failed payment
                |--------------------------------------------------------------------------
                */
                if ($eventType === 'payment.failed' && $lockedPayment->status !== 'paid') {
                    $paymentBecameFailed = $lockedPayment->status !== 'failed';
                    $lockedPayment->status = 'failed';

                    $failureReason =
                        $paymentAttributes['last_payment_error']['message']
                        ?? $paymentAttributes['failure_reason']
                        ?? $paymentAttributes['description']
                        ?? 'PayMongo payment failed.';

                    $lockedPayment->failure_reason =
                        $failureReason;

                    $lockedPayment->save();

                    Log::warning(
                        'PayMongo payment failed.',
                        [
                            'payment_id' => $lockedPayment->id,
                            'gateway_payment_id' =>
                                $lockedPayment->gateway_payment_id,
                            'reason' => $failureReason,
                        ]
                    );
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Send notification AFTER successful DB transaction
            |--------------------------------------------------------------------------
            */
            if ($paymentBecamePaid && in_array(
                $eventType,
                ['checkout_session.payment.paid', 'payment.paid'],
                true
            )) {
                $freshPayment = Payment::find($payment->id);

                if ($freshPayment) {
                    $order = Order::find($freshPayment->order_id);

                    if ($order) {
                        /*
                        |--------------------------------------------------------------------------
                        | Safe amount formatting
                        |--------------------------------------------------------------------------
                        */
                        $paidAmount = (float) (
                            $freshPayment->amount ?? 0
                        );

                        $message =
                            'Your online payment of ₱'
                            . number_format($paidAmount, 2)
                            . ' for order #'
                            . $order->order_number
                            . ' has been confirmed.';

                        /*
                        |--------------------------------------------------------------------------
                        | NotificationService
                        |
                        | If your existing NotificationService has a different
                        | method signature, keep that service's existing method
                        | and only retain the safe $paidAmount conversion above.
                        |--------------------------------------------------------------------------
                        */
                        try {
                            $notificationService = app(NotificationService::class);

                            /*
                            |--------------------------------------------------------------------------
                            | Payment notification
                            |--------------------------------------------------------------------------
                            |
                            | Use the existing NotificationService implementation.
                            | Do not call an undefined send() method.
                            |
                            */

                            if (method_exists($notificationService, 'sendToUser')) {
                                $notificationService->sendToUser(
                                    $order->customer->user,
                                    'Payment Confirmed',
                                    $message,
                                    [
                                        'entity_type' => 'order',
                                        'entity_id' => (string) $order->id,
                                    ]
                                );
                            }

                        } catch (\Throwable $e) {
                            Log::warning(
                                'PayMongo customer notification failed.',
                                [
                                    'payment_id' => $freshPayment->id,
                                    'error' => $e->getMessage(),
                                ]
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Realtime signal
                        |--------------------------------------------------------------------------
                        */
                        try {
                            app(RealtimeEventService::class)->emit(
                                'order',
                                (string) $order->id,
                                'payment_status_changed',
                                'paid'
                            );
                            app(RealtimeEventService::class)->emit(
                                'payment',
                                (string) $freshPayment->id,
                                'payment_status_changed',
                                'paid'
                            );
                        } catch (\Throwable $e) {
                            Log::warning(
                                'PayMongo realtime event failed.',
                                [
                                    'payment_id' => $freshPayment->id,
                                    'error' => $e->getMessage(),
                                ]
                            );
                        }
                    }
                }
            }

            if ($paymentBecameFailed) {
                app(RealtimeEventService::class)->emit(
                    'payment',
                    (string) $payment->id,
                    'payment_status_changed',
                    'failed'
                );
                app(RealtimeEventService::class)->emit(
                    'order',
                    (string) $payment->order_id,
                    'payment_status_changed',
                    'failed'
                );

                $failedOrder = Order::with('customer.user')->find($payment->order_id);
                if ($failedOrder?->customer?->user) {
                    try {
                        app(NotificationService::class)->sendToUser(
                            $failedOrder->customer->user,
                            'Payment Not Completed',
                            'Payment could not be completed. Please try again.',
                            ['entity_type' => 'order', 'entity_id' => (string) $failedOrder->id]
                        );
                    } catch (\Throwable $e) {
                        Log::warning('PayMongo failure notification failed.', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'PayMongo webhook processed successfully.',
                'event_id' => $eventId,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('PayMongo webhook processing failed.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payment_id' => $payment->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }

    /**
     * Verify PayMongo webhook signature.
     *
     * PayMongo signs:
     *
     * timestamp + "." + RAW JSON PAYLOAD
     *
     * using HMAC SHA-256.
     */
    private function hasValidSignature(
        Request $request,
        string $payload
    ): bool {
        $secret = config('services.paymongo.webhook_secret');

        if (!$secret) {
            Log::error(
                'PAYMONGO_WEBHOOK_SECRET is not configured.'
            );

            return false;
        }

        $header = $request->header(
            'Paymongo-Signature',
            $request->header('X-Paymongo-Signature', '')
        );

        if (!$header) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Parse:
        |
        | t=timestamp,te=test_signature,li=live_signature
        |--------------------------------------------------------------------------
        */
        $parts = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', $part, 2);

            if (count($pair) === 2) {
                $parts[trim($pair[0])] =
                    trim($pair[1]);
            }
        }

        $timestamp = $parts['t'] ?? null;
        $testSignature = $parts['te'] ?? null;
        $liveSignature = $parts['li'] ?? null;

        if (!$timestamp) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Determine whether the webhook is test/live from payload.
        |--------------------------------------------------------------------------
        */
        $decoded = json_decode($payload, true);

        $livemode = (bool) (
            data_get($decoded, 'data.livemode')
            ?? data_get($decoded, 'data.attributes.livemode', false)
        );

        $providedSignature = $livemode
            ? $liveSignature
            : $testSignature;

        if (!$providedSignature) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Generate expected signature
        |--------------------------------------------------------------------------
        */
        $signedPayload =
            $timestamp
            . '.'
            . $payload;

        $expectedSignature =
            hash_hmac(
                'sha256',
                $signedPayload,
                $secret
            );

        return hash_equals(
            $expectedSignature,
            $providedSignature
        );
    }

    /**
     * Synchronize a pending payment with PayMongo Hosted Checkout session.
     */
    public function syncPaymentWithGateway(
        Payment $payment,
        Order $order,
        PayMongoService $payMongo
    ): Payment {
        if ($payment->status === 'paid' || blank($payment->gateway_checkout_session_id)) {
            return $payment;
        }

        $sessionData = $payMongo->retrieveCheckout($payment->gateway_checkout_session_id);
        if (!$sessionData) {
            return $payment;
        }

        $attributes = data_get($sessionData, 'data.attributes', []);
        $payments = data_get($attributes, 'payments', []);
        $sessionStatus = data_get($attributes, 'status');

        $paidPayment = null;
        if (is_array($payments)) {
            foreach ($payments as $p) {
                if (data_get($p, 'attributes.status') === 'paid') {
                    $paidPayment = $p;
                    break;
                }
            }
        }

        if ($paidPayment) {
            $paymentId = data_get($paidPayment, 'id');
            $paymentAttributes = data_get($paidPayment, 'attributes', []);
            $this->confirmPaidPayment(
                $payment,
                $order,
                $paymentId,
                $paymentAttributes,
                'sync-' . $payment->gateway_checkout_session_id
            );
            return $payment->fresh();
        }

        if ($sessionStatus === 'paid') {
            $paymentIntentId = data_get($attributes, 'payment_intent.id');
            $this->confirmPaidPayment(
                $payment,
                $order,
                $paymentIntentId,
                [],
                'sync-session-' . $payment->gateway_checkout_session_id
            );
            return $payment->fresh();
        }

        if (in_array($sessionStatus, ['expired', 'cancelled'], true) && $payment->status === 'pending') {
            $payment->update([
                'status' => 'cancelled',
                'failure_reason' => 'PayMongo checkout session ' . $sessionStatus,
            ]);
        }

        return $payment->fresh();
    }

    /**
     * Mark a payment as paid, transition the order workflow, emit realtime events, and notify customer.
     */
    public function confirmPaidPayment(
        Payment $payment,
        Order $order,
        ?string $paymentId = null,
        array $paymentAttributes = [],
        ?string $eventId = null,
        bool $livemode = false
    ): void {
        $paymentBecamePaid = false;

        DB::transaction(function () use (
            $payment,
            $order,
            $paymentId,
            $paymentAttributes,
            $eventId,
            $livemode,
            &$paymentBecamePaid
        ) {
            $lockedPayment = Payment::whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedPayment) return;

            $paymentBecamePaid = $lockedPayment->status !== 'paid';
            $lockedPayment->status = 'paid';

            $amount = (float) ($lockedPayment->amount ?? 0);

            if (!empty($paymentId)) {
                $lockedPayment->gateway_payment_id = $paymentId;
            }

            if (array_key_exists('fee', $paymentAttributes)) {
                $lockedPayment->gateway_fee =
                    isset($paymentAttributes['fee'])
                    ? ((float) $paymentAttributes['fee'] / 100)
                    : null;
            }

            if (array_key_exists('net_amount', $paymentAttributes)) {
                $lockedPayment->gateway_net_amount =
                    isset($paymentAttributes['net_amount'])
                    ? ((float) $paymentAttributes['net_amount'] / 100)
                    : null;
            }

            $lockedPayment->paid_at ??= now();
            $lockedPayment->failure_reason = null;
            $lockedPayment->save();

            $freshOrder = Order::find($order->id);
            if ($freshOrder && $freshOrder->status === 'pending') {
                app(OrderWorkflowService::class)->transition($freshOrder, 'confirmed');
            }

            if ($paymentBecamePaid) {
                try {
                    AuditLog::create([
                        'user_id' => null,
                        'action' => 'payment.paid',
                        'entity_type' => 'payment',
                        'entity_id' => $lockedPayment->id,
                        'description' =>
                            'PayMongo payment confirmed for order #'
                            . ($freshOrder?->order_number ?? $order->order_number)
                            . ' - Amount: ₱'
                            . number_format($amount, 2),
                        'metadata' => [
                            'gateway' => 'paymongo',
                            'gateway_payment_id' => $lockedPayment->gateway_payment_id,
                            'gateway_checkout_session_id' => $lockedPayment->gateway_checkout_session_id,
                            'gateway_reference' => $lockedPayment->gateway_reference,
                            'event_id' => $eventId,
                            'livemode' => $livemode,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('PayMongo payment audit log failed: ' . $e->getMessage());
                }
            }
        });

        if ($paymentBecamePaid) {
            $freshPayment = Payment::find($payment->id);
            $orderToNotify = Order::with('customer.user')->find($order->id);

            if ($orderToNotify && $freshPayment) {
                $paidAmount = (float) ($freshPayment->amount ?? 0);
                $message =
                    'Your online payment of ₱'
                    . number_format($paidAmount, 2)
                    . ' for order #'
                    . $orderToNotify->order_number
                    . ' has been confirmed.';

                try {
                    $notificationService = app(NotificationService::class);
                    if ($orderToNotify->customer?->user && method_exists($notificationService, 'sendToUser')) {
                        $notificationService->sendToUser(
                            $orderToNotify->customer->user,
                            'Payment Confirmed',
                            $message,
                            [
                                'entity_type' => 'order',
                                'entity_id' => (string) $orderToNotify->id,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('PayMongo customer notification failed: ' . $e->getMessage());
                }

                try {
                    app(RealtimeEventService::class)->emit(
                        'order',
                        (string) $orderToNotify->id,
                        'payment_status_changed',
                        'paid'
                    );
                    app(RealtimeEventService::class)->emit(
                        'payment',
                        (string) $freshPayment->id,
                        'payment_status_changed',
                        'paid'
                    );
                } catch (\Throwable $e) {
                    Log::warning('PayMongo realtime event failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Testing endpoint to simulate a successful payment in dev/testing mode.
     */
    public function simulateSuccess(
        Request $request,
        Order $order
    ): JsonResponse {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $customer = $user->customer;
        if (!$customer || (string) $order->customer_id !== (string) $customer->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $payment = Payment::where('order_id', $order->id)->first();
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment record not found.'], 404);
        }

        $this->confirmPaidPayment(
            $payment,
            $order,
            'test_pay_' . \Illuminate\Support\Str::random(16),
            ['fee' => 1500, 'net_amount' => ((float) $payment->amount * 100) - 1500]
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment marked as paid for testing.',
            'data' => [
                'order_id' => $order->id,
                'status' => 'paid',
                'order_status' => $order->fresh()->status,
            ],
        ]);
    }
}
