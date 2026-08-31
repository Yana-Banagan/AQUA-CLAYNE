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
        ]);

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
            ->with('items.product')
            ->latest()
            ->get();

        return $this->success($orders);
    }
}
