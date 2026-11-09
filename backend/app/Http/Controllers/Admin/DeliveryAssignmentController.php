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
            ['confirmed']
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

    public function store(Request $request, ?Order $order = null)
    {
        $deliveryId = $request->input('delivery_id');
        $orderId = $order ? $order->id : $request->input('order_id');

        if (!$orderId && $deliveryId) {
            $existingDelivery = \App\Models\Delivery::find($deliveryId);
            if ($existingDelivery) {
                $orderId = $existingDelivery->order_id;
            }
        }

        $request->merge(['order_id' => $orderId]);

        $data = $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'driver_id' => 'required|uuid|exists:drivers,id',
            'eta' => 'nullable|string|max:100',
        ]);

        $order = Order::findOrFail($data['order_id']);
        $driver = Driver::with('user')->findOrFail($data['driver_id']);
        $eta = $data['eta'] ?? null;

        try {
            $delivery = $this->deliveryService->assign(
                $order,
                $driver,
                $request->user(),
                $eta
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Order #{$order->order_number} successfully assigned to {$driver->user->name}.",
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => 'assigned',
                        'driver_name' => $driver->user->name,
                        'driver_initials' => strtoupper(substr($driver->user->name, 0, 2)),
                        'delivery_id' => $delivery->id,
                        'eta' => $delivery->eta,
                    ],
                ]);
            }

            return back()->with('success', "Order #{$order->order_number} assigned to {$driver->user->name}.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                $firstError = collect($e->errors())->flatten()->first();
                return response()->json([
                    'success' => false,
                    'message' => $firstError ?: 'Validation error occurred during assignment.',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Unable to assign the driver. Please try again.',
                ], 500);
            }
            return back()->withErrors(['assignment' => $e->getMessage()]);
        }
    }
}
