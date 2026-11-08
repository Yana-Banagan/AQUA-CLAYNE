<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\DeliveryRestrictionService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private PaymentService $paymentService
    ) {
    }

    public function create(
        Customer $customer,
        array $data
    ): Order {
        return DB::transaction(
            function () use ($customer, $data) {

                // Serialize sequence allocation per customer. The UUID remains
                // the primary key; order_number is the friendly number shown
                // to that customer and begins at 01.
                $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

                // A timed-out/retried mobile submission reuses its client key
                // and receives the original order instead of creating a new
                // order and payment transaction.
                if (!empty($data['client_request_id'])) {
                    $existingOrder = $customer->orders()
                        ->where('client_request_id', $data['client_request_id'])
                        ->with('items.product', 'payment', 'statusHistory')
                        ->first();
                    if ($existingOrder) return $existingOrder;
                }

                $nextOrderNumber = str_pad(
                    (string) ($customer->orders()->count() + 1),
                    2,
                    '0',
                    STR_PAD_LEFT
                );

                $total = 0;
                $itemsData = [];

                foreach ($data['items'] as $item) {

                    $product = Product::with('inventory')
                        ->findOrFail(
                            $item['product_id']
                        );

                    if (!$product->is_active || stripos($product->name, 'alkaline') !== false) {
                        throw new \Exception(
                            stripos($product->name, 'alkaline') !== false
                                ? "Alkaline products are no longer available."
                                : "{$product->name} is not available."
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

                /*
                 * If payment_method was not supplied,
                 * default to cash.
                 */
                $paymentMethod =
                    $data['payment_method'] ?? 'cash';

                $deliveryMethod = $data['delivery_method'] ?? 'home_delivery';
                $deliveryFee = $deliveryMethod === 'station_pickup' ? 0.00 : 5.00;
                $addressId = $deliveryMethod === 'home_delivery' ? ($data['address_id'] ?? null) : null;

                if ($deliveryMethod === 'home_delivery') {
                    if (!$addressId) {
                        throw new \Exception('A valid delivery address is required for Home Delivery.');
                    }

                    $address = $customer->addresses()->find($addressId);
                    if (!$address || !DeliveryRestrictionService::isDeliveryAddressAllowed($address)) {
                        throw new \Exception(DeliveryRestrictionService::RESTRICTION_MESSAGE);
                    }
                }

                $totalWithDelivery = round($total + $deliveryFee, 2);

                $order = Order::create([
                    'customer_id' =>
                        $customer->id,

                    'address_id' =>
                        $addressId,

                    'delivery_method' =>
                        $deliveryMethod,

                    'delivery_fee' =>
                        $deliveryFee,

                    'status' =>
                        'pending',

                    'order_number' => $nextOrderNumber,

                    'client_request_id' => $data['client_request_id'] ?? null,

                    'container_ownership' =>
                        $data['container_ownership'] ??
                        'customer_owned',

                    'expected_empty_gallons' =>
                        $data['expected_empty_gallons'],

                    'payment_method' =>
                        $paymentMethod,

                    'notes' =>
                        $data['notes'] ?? null,

                    'total_amount' =>
                        $totalWithDelivery,
                ]);

                /*
                 * Create order items first.
                 */
                $order->items()->createMany(
                    $itemsData
                );

                /*
                 * STEP 12
                 *
                 * Create the payment through
                 * PaymentService.
                 *
                 * This happens inside the same
                 * checkout transaction.
                 */
                $payment = $this->paymentService
                    ->createForOrder(
                        $order,
                        $paymentMethod
                    );

                /*
                 * Record initial order status.
                 */
                OrderStatusHistory::create([
                    'order_id' =>
                        $order->id,

                    'to_status' =>
                        'pending',

                    'changed_by' =>
                        $customer->user_id,

                    'actor_role' =>
                        'customer',
                ]);

                /*
                 * PHASE 9 — REALTIME EVENT
                 */
                app(RealtimeEventService::class)->emit(
                    'order',
                    $order->id,
                    'created',
                    $order->status
                );

                /*
                 * Return the order together with
                 * its related data.
                 */
                return $order->load(
                    'items.product',
                    'payment',
                    'statusHistory'
                );
            }
        );
    }
}
