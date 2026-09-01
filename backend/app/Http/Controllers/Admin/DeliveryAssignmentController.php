<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryAssignmentController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {
    }

    public function index()
    {
        $pendingOrders = Order::whereIn(
            'status',
            ['pending', 'confirmed']
        )
            ->with('customer.user')
            ->get();

        $activeDrivers = Driver::whereHas(
            'user',
            fn($q) => $q->where(
                'status',
                'active'
            )
        )
            ->with('user')
            ->get();

        return view(
            'admin.deliveries.assign',
            compact(
                'pendingOrders',
                'activeDrivers'
            )
        );
    }

    public function store(
        Request $request
    ) {
        $data = $request->validate([
            'order_id' =>
                'required|uuid|exists:orders,id',

            'driver_id' =>
                'required|uuid|exists:drivers,id',
        ]);

        $order = Order::findOrFail(
            $data['order_id']
        );

        $driver = Driver::findOrFail(
            $data['driver_id']
        );

        $this->deliveryService->assign(
            $order,
            $driver
        );

        return back()->with(
            'success',
            'Driver assigned.'
        );
    }
}
