<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function create(
        Customer $customer,
        array $data
    ): Order {
        return DB::transaction(
            function () use ($customer, $data) {

                $total = 0;
                $itemsData = [];

                foreach ($data['items'] as $item) {

                    $product = Product::with('inventory')
                        ->findOrFail(
                            $item['product_id']
                        );

                    if (!$product->is_active) {
                        throw new \Exception(
                            "{$product->name} is not available."
                        );
                    }

                    $lineTotal =
                        $product->price *
                        $item['quantity'];

                    $total += $lineTotal;

                    $itemsData[] = [
                        'product_id' =>
                            $product->id,

                        'quantity' =>
                            $item['quantity'],

                        'unit_price' =>
                            $product->price,
                    ];
                }

                $order = Order::create([
                    'customer_id' =>
                        $customer->id,

                    'address_id' =>
                        $data['address_id'],

                    'status' =>
                        'pending',

                    'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr((string) \Illuminate\Support\Str::uuid(), 0, 8)),

                    'container_ownership' => $data['container_ownership'],

                    'expected_empty_gallons' => $data['expected_empty_gallons'],

                    'payment_method' => $data['payment_method'],

                    'notes' => $data['notes'] ?? null,

                    'total_amount' =>
                        $total,
                ]);

                $order->items()->createMany(
                    $itemsData
                );

                Payment::create([
                    'order_id' => $order->id,
                    'method' => $data['payment_method'],
                    'amount' => $total,
                    'status' => $data['payment_method'] === 'credit' ? 'verified' : 'pending',
                    'paid_at' => $data['payment_method'] === 'credit' ? now() : null,
                ]);

                if ($data['payment_method'] === 'credit') {
                    app(CreditService::class)->charge($customer, $order, (float) $total);
                }

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'to_status' => 'pending',
                    'changed_by' => $customer->user_id,
                    'actor_role' => 'customer',
                ]);

                // ----------------------------------------------------
                // PHASE 9 — REALTIME EVENT
                // ----------------------------------------------------
                app(RealtimeEventService::class)->emit(
                    'order',
                    $order->id,
                    'created',
                    $order->status
                );

                return $order->load(
                    'items.product', 'payments', 'statusHistory'
                );
            }
        );
    }
}
