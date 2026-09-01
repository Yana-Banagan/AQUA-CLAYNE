<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {
    }

    /**
     * Get the authenticated driver's Driver record.
     */
    private function driver(Request $request): Driver
    {
        return $request->user()->driver;
    }

    /**
     * Get active/non-completed deliveries
     * belonging ONLY to the authenticated driver.
     */
    public function index(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereNotIn(
                'status',
                ['delivered', 'failed']
            )
            ->with([
                'order.customer.user',
                'order.address',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get completed delivery history
     * belonging ONLY to the authenticated driver.
     */
    public function history(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereIn(
                'status',
                ['delivered', 'failed']
            )
            ->with([
                'order',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get one delivery.
     *
     * The delivery MUST belong to the authenticated driver.
     */
    public function show(
        Request $request,
        Delivery $delivery
    ) {
        $driver = $this->driver($request);

        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to access this delivery.'
        );

        $delivery->load([
            'order.items.product',
            'order.customer.user',
            'order.address',
        ]);

        return $this->success($delivery);
    }

    /**
     * Update delivery status.
     *
     * The delivery MUST belong to the authenticated driver.
     */
    public function updateStatus(
        Request $request,
        Delivery $delivery
    ) {
        $driver = $this->driver($request);

        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to update this delivery.'
        );

        $data = $request->validate([
            'status' => [
                'required',
                'in:assigned,en_route,delivered,failed',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'eta' => [
                'nullable',
                'date',
            ],
        ]);

        $updated = $this->deliveryService->updateStatus(
            $delivery,
            $data['status'],
            $data['note'] ?? null,
            $data['eta'] ?? null
        );

        return $this->success($updated);
    }
}
