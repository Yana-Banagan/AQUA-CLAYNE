<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Services\RealtimeEventService;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function assign(
        Order $order,
        Driver $driver
    ): Delivery {
        return DB::transaction(
            function () use ($order, $driver) {

                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'status' => 'assigned',
                ]);

                $order->update([
                    'status' => 'assigned',
                ]);

                DeliveryStatusHistory::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'assigned',
                    'note' => 'Assigned by admin',
                ]);

                app(RealtimeEventService::class)->emit(
                    'delivery',
                    $delivery->id,
                    'assigned',
                    $delivery->status
                );

                return $delivery;
            }
        );
    }

    public function updateStatus(
        Delivery $delivery,
        string $status,
        ?string $note = null,
        ?string $eta = null
    ): Delivery {
        return DB::transaction(
            function () use ($delivery, $status, $note, $eta) {

                $delivery->update([
                    'status' => $status,
                    'eta' => $eta ?? $delivery->eta,
                ]);

                if (
                    in_array(
                        $status,
                        ['delivered', 'failed']
                    )
                ) {
                    $delivery->order->update([
                        'status' =>
                            $status === 'delivered'
                            ? 'delivered'
                            : 'cancelled',
                    ]);
                } elseif ($status === 'en_route') {
                    $delivery->order->update([
                        'status' => 'out_for_delivery',
                    ]);
                }

                DeliveryStatusHistory::create([
                    'delivery_id' => $delivery->id,
                    'status' => $status,
                    'note' => $note,
                ]);

                app(RealtimeEventService::class)->emit(
                    'delivery',
                    $delivery->id,
                    'status_changed',
                    $status
                );

                return $delivery->fresh();
            }
        );
    }
}
