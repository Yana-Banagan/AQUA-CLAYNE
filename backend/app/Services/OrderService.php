<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
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

                    if (
                        !$product->inventory ||
                        $product->inventory
                            ->quantity_on_hand
                        < $item['quantity']
                    ) {
                        throw new \Exception(
                            "Insufficient stock for {$product->name}."
                        );
                    }

                    $product->inventory->decrement(
                        'quantity_on_hand',
                        $item['quantity']
                    );

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

                    'total_amount' =>
                        $total,
                ]);

                $order->items()->createMany(
                    $itemsData
                );

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
                    'items.product'
                );
            }
        );
    }
}
