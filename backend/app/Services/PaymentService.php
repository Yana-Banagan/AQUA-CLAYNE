<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private CreditService $creditService
    ) {
    }

    public function createForOrder(
        Order $order,
        string $method
    ): Payment {
        if ($method === 'debt') {
            return Payment::create([
                'order_id' => $order->id,
                'method' => 'debt',
                'amount' => $order->total_amount,
                'status' => 'pending',
            ]);
        }

        if ($method === 'credit') {
            $this->creditService->charge(
                $order->customer,
                $order,
                (float) $order->total_amount
            );

            return Payment::create([
                'order_id' => $order->id,
                'method' => 'credit',
                'amount' => $order->total_amount,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        if ($method === 'online') {
            return Payment::create([
                'order_id' => $order->id,
                'method' => 'online',
                'amount' => $order->total_amount,
                'status' => 'pending',
                'gateway' => 'paymongo',
                'gateway_reference' => "AC-ORDER-{$order->id}",
            ]);
        }

        // Cash is collected later by the driver.
        return Payment::create([
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => $order->total_amount,
            'status' => 'pending',
        ]);
    }

    public function collectCash(
        Payment $payment,
        ?User $actor = null
    ): Payment {
        return DB::transaction(function () use ($payment, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->method !== 'cash' || $payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => ['Only a pending cash payment can be collected.'],
                ]);
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'payment.cash_collected',
                'table_name' => 'payments',
                'record_id' => $payment->id,
            ]);

            app(RealtimeEventService::class)->emit('payment', $payment->id, 'payment_status_changed', 'paid');

            return $payment->fresh();
        });
    }
}
