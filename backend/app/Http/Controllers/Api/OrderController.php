<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;


class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'address_id' =>
                'required|uuid|exists:customer_addresses,id',

            'items' =>
                'required|array|min:1',

            'items.*.product_id' =>
                'required|uuid|exists:products,id',

            'items.*.quantity' =>
                'required|integer|min:1',

            'container_ownership' => 'sometimes|in:customer_owned',
            'expected_empty_gallons' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,credit',
            'notes' => 'nullable|string|max:2000',
        ]);

        $data['container_ownership'] = $data['container_ownership'] ?? 'customer_owned';

        if (!$request->user()->customer->addresses()->whereKey($data['address_id'])->exists()) {
            return $this->error('The selected address does not belong to you.', 403);
        }

        try {

            $order = $this->orderService->create(
                $request->user()->customer,
                $data
            );

            return $this->success(
                $order,
                'Order placed',
                201
            );

        } catch (\Exception $e) {

            return $this->error(
                $e->getMessage(),
                422
            );
        }
    }

    public function index(Request $request)
    {
        $orders = $request
            ->user()
            ->customer
            ->orders()
            ->with('items.product', 'payments', 'containerIntake', 'refillQueueEntry', 'statusHistory')
            ->latest()
            ->get();

        return $this->success($orders);
    }

    public function show(Request $request, \App\Models\Order $order)
    {
        abort_unless($order->customer_id === $request->user()->customer->id, 403);
        return $this->success($order->load('items.product', 'payments', 'containerIntake', 'refillQueueEntry', 'statusHistory'));
    }

    public function cancel(Request $request, \App\Models\Order $order, \App\Services\OrderWorkflowService $workflow)
    {
        abort_unless($order->customer_id === $request->user()->customer->id, 403);
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        if ($order->status !== 'pending')
            return $this->error('This order can no longer be cancelled by the customer.', 422);
        return $this->success($workflow->transition($order, 'cancelled', $request->user(), $data['reason'] ?? null), 'Order cancelled.');
    }
}
