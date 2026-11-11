<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CustomerContainerRfidTag;
use App\Models\Order;
use App\Models\RfidDevice;
use App\Models\RfidTag;
use App\Models\RfidTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RfidTransactionService
{
    /**
     * Validate and log an operational RFID scan (e.g., station intake, refill, or delivery).
     */
    public function validateAndRecordScan(
        RfidDevice $device,
        string $rawUid,
        string $scanType,
        ?string $orderId = null,
        ?User $actor = null
    ): array {
        $uid = strtoupper(trim(preg_replace('/[\s:]+/', '', $rawUid)));

        if (empty($uid)) {
            return [
                'success' => false,
                'error' => 'INVALID_UID',
                'message' => 'Scanned RFID UID is empty or invalid.',
            ];
        }

        // 1. Look up active customer container tag
        $customerTag = CustomerContainerRfidTag::with(['container.customer.user'])
            ->where('uid', $uid)
            ->where('status', 'active')
            ->first();

        // 2. Also look up station loaner gallon tag if not customer container
        $stationTag = null;
        if (!$customerTag) {
            $stationTag = RfidTag::with('gallon')->where('tag_uid', $uid)->first();
        }

        if (!$customerTag && !$stationTag) {
            // Log unknown tag scan for security monitoring
            RfidTransaction::create([
                'device_id' => $device->id,
                'action' => strtolower($scanType),
                'transaction_type' => strtoupper($scanType),
                'scan_result' => 'rejected',
                'message' => "Unknown or unregistered RFID tag scanned: {$uid}",
                'scanned_by' => $actor?->id,
                'scanned_at' => now(),
                'metadata' => ['raw_uid' => $uid],
            ]);

            return [
                'success' => false,
                'error' => 'TAG_NOT_FOUND',
                'message' => "RFID UID {$uid} is not registered or active.",
            ];
        }

        $container = $customerTag?->container;
        if ($container && $container->status !== 'active') {
            RfidTransaction::create([
                'customer_container_id' => $container->id,
                'customer_container_rfid_tag_id' => $customerTag->id,
                'device_id' => $device->id,
                'action' => strtolower($scanType),
                'transaction_type' => strtoupper($scanType),
                'scan_result' => 'rejected',
                'message' => "Container {$container->container_code} is {$container->status}.",
                'scanned_by' => $actor?->id,
                'scanned_at' => now(),
            ]);

            return [
                'success' => false,
                'error' => 'CONTAINER_NOT_ACTIVE',
                'message' => "Container {$container->container_code} is marked {$container->status}.",
            ];
        }

        // 3. Process according to scan type
        $order = $orderId ? Order::with('customer.user')->find($orderId) : null;

        if (strtoupper($scanType) === 'STATION_INTAKE' && $order) {
            // Verify that container belongs to the order's customer
            if ($container && $container->customer_id !== $order->customer_id) {
                RfidTransaction::create([
                    'customer_container_id' => $container->id,
                    'customer_container_rfid_tag_id' => $customerTag->id,
                    'device_id' => $device->id,
                    'order_id' => $order->id,
                    'action' => 'station_intake',
                    'transaction_type' => 'STATION_INTAKE',
                    'scan_result' => 'rejected',
                    'message' => "Container belongs to customer {$container->customer?->user?->name}, but order is for {$order->customer?->user?->name}.",
                    'scanned_by' => $actor?->id,
                    'scanned_at' => now(),
                ]);

                return [
                    'success' => false,
                    'error' => 'CUSTOMER_MISMATCH',
                    'message' => 'Scanned gallon does not belong to this order’s customer.',
                ];
            }

            // Verify order state
            if (!in_array($order->status, ['picked_up', 'confirmed', 'pending'], true)) {
                return [
                    'success' => false,
                    'error' => 'ORDER_NOT_AWAITING_INTAKE',
                    'message' => "Order #{$order->order_number} is in '{$order->status}' state and not ready for intake.",
                ];
            }

            // Check duplicate intake scan within recent window
            $recentScan = RfidTransaction::where('customer_container_id', $container?->id)
                ->where('order_id', $order->id)
                ->where('transaction_type', 'STATION_INTAKE')
                ->where('scan_result', 'accepted')
                ->where('scanned_at', '>=', now()->subMinutes(5))
                ->first();

            if ($recentScan) {
                return [
                    'success' => true,
                    'message' => 'Gallon intake was already recorded.',
                    'already_scanned' => true,
                    'container' => [
                        'code' => $container?->container_code,
                        'type' => $container?->container_type,
                    ],
                ];
            }
        }

        // Record successful transaction
        $transaction = RfidTransaction::create([
            'rfid_tag_id' => $stationTag?->id,
            'customer_container_id' => $container?->id,
            'customer_container_rfid_tag_id' => $customerTag?->id,
            'device_id' => $device->id,
            'order_id' => $order?->id,
            'action' => strtolower($scanType),
            'transaction_type' => strtoupper($scanType),
            'previous_status' => $order?->status,
            'new_status' => $order?->status,
            'scan_result' => 'accepted',
            'message' => "Successfully validated RFID scan ({$scanType}) for tag {$uid}.",
            'scanned_by' => $actor?->id,
            'scanned_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'RFID tag verified successfully.',
            'transaction_id' => $transaction->id,
            'uid' => $uid,
            'container' => $container ? [
                'id' => $container->id,
                'code' => $container->container_code,
                'type' => $container->container_type,
                'customer_name' => $container->customer?->user?->name,
            ] : null,
            'station_gallon' => $stationTag ? [
                'id' => $stationTag->gallon_id,
                'type' => $stationTag->gallon?->type,
            ] : null,
        ];
    }
}

