<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CustomerContainer;
use App\Models\CustomerContainerRfidTag;
use App\Models\RfidDevice;
use App\Models\RfidRegistrationSession;
use App\Models\RfidTag;
use App\Models\RfidTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfidRegistrationService
{
    /**
     * Start a temporary RFID registration session.
     */
    public function createSession(string $containerId, User $admin, ?string $deviceId = null): RfidRegistrationSession
    {
        $container = CustomerContainer::findOrFail($containerId);

        if ($container->status !== 'active') {
            throw ValidationException::withMessages([
                'customer_container_id' => ["Container is currently {$container->status} and cannot be assigned an RFID tag."],
            ]);
        }

        // Check if container already has an active tag
        $activeTag = $container->activeTag;
        if ($activeTag) {
            throw ValidationException::withMessages([
                'customer_container_id' => ["This container is already linked to active tag UID: {$activeTag->uid}. Use Replace Tag instead."],
            ]);
        }

        // Determine target station device
        $device = null;
        if ($deviceId) {
            $device = RfidDevice::find($deviceId);
        }
        if (!$device) {
            $device = RfidDevice::where('status', 'online')->orderByDesc('last_heartbeat_at')->first();
        }
        if (!$device) {
            $device = RfidDevice::first();
        }

        return DB::transaction(function () use ($container, $admin, $device) {
            // Cancel any stale pending sessions for this container
            RfidRegistrationSession::where('customer_container_id', $container->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            return RfidRegistrationSession::create([
                'customer_container_id' => $container->id,
                'station_device_id' => $device?->id,
                'created_by' => $admin->id,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(3),
            ]);
        });
    }

    /**
     * Cancel an active registration session.
     */
    public function cancelSession(RfidRegistrationSession $session, User $admin): RfidRegistrationSession
    {
        if ($session->status === 'pending') {
            $session->status = 'cancelled';
            $session->save();

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'rfid.session_cancelled',
                'table_name' => 'rfid_registration_sessions',
                'record_id' => $session->id,
            ]);
        }

        return $session;
    }

    /**
     * Complete an RFID registration scan submitted by an ESP32 device.
     */
    public function completeRegistration(RfidDevice $device, string $sessionId, string $rawUid): array
    {
        $uid = strtoupper(trim(preg_replace('/[\s:]+/', '', $rawUid)));

        if (empty($uid)) {
            return [
                'success' => false,
                'error' => 'INVALID_UID',
                'message' => 'Scanned RFID UID is empty or invalid.',
            ];
        }

        return DB::transaction(function () use ($device, $sessionId, $uid) {
            /** @var RfidRegistrationSession|null $session */
            $session = RfidRegistrationSession::where('id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return [
                    'success' => false,
                    'error' => 'SESSION_NOT_FOUND',
                    'message' => 'Registration session does not exist.',
                ];
            }

            if ($session->station_device_id && $session->station_device_id !== $device->id) {
                return [
                    'success' => false,
                    'error' => 'DEVICE_MISMATCH',
                    'message' => 'This session was assigned to a different RFID scanner.',
                ];
            }

            if ($session->status !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'SESSION_' . strtoupper($session->status),
                    'message' => "Registration session is {$session->status}.",
                ];
            }

            if ($session->expires_at->isPast()) {
                $session->status = 'expired';
                $session->save();

                return [
                    'success' => false,
                    'error' => 'SESSION_EXPIRED',
                    'message' => 'Registration session has expired. Please restart registration.',
                ];
            }

            /** @var CustomerContainer $container */
            $container = CustomerContainer::where('id', $session->customer_container_id)
                ->lockForUpdate()
                ->first();

            if (!$container || $container->status !== 'active') {
                return [
                    'success' => false,
                    'error' => 'CONTAINER_INACTIVE',
                    'message' => 'Customer container is no longer active.',
                ];
            }

            // Check if UID is already active in customer tags
            $existingCustomerTag = CustomerContainerRfidTag::where('uid', $uid)
                ->where('status', 'active')
                ->first();

            if ($existingCustomerTag) {
                return [
                    'success' => false,
                    'error' => 'DUPLICATE_RFID_TAG',
                    'message' => "RFID UID {$uid} is already assigned to container {$existingCustomerTag->container?->container_code}.",
                ];
            }

            // Check if UID is in station asset tags
            $existingStationTag = RfidTag::where('tag_uid', $uid)->first();
            if ($existingStationTag) {
                return [
                    'success' => false,
                    'error' => 'DUPLICATE_RFID_TAG',
                    'message' => "RFID UID {$uid} is already registered to station asset #{$existingStationTag->gallon_id}.",
                ];
            }

            // Check if container already has an active tag
            $hasActiveTag = CustomerContainerRfidTag::where('customer_container_id', $container->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTag) {
                return [
                    'success' => false,
                    'error' => 'CONTAINER_ALREADY_TAGGED',
                    'message' => 'This container already has an active RFID tag assigned.',
                ];
            }

            // Create the new active RFID tag association
            $tag = CustomerContainerRfidTag::create([
                'customer_container_id' => $container->id,
                'uid' => $uid,
                'status' => 'active',
                'registered_at' => now(),
            ]);

            // Mark session completed
            $session->status = 'completed';
            $session->completed_at = now();
            $session->scanned_uid = $uid;
            $session->save();

            // Record transaction
            $transaction = RfidTransaction::create([
                'customer_container_id' => $container->id,
                'customer_container_rfid_tag_id' => $tag->id,
                'device_id' => $device->id,
                'action' => 'registration',
                'transaction_type' => 'REGISTRATION',
                'previous_status' => null,
                'new_status' => 'active',
                'scan_result' => 'accepted',
                'message' => "Successfully registered RFID tag {$uid} to container {$container->container_code}.",
                'scanned_by' => $session->created_by,
                'scanned_at' => now(),
            ]);

            // Audit log
            AuditLog::create([
                'actor_id' => $session->created_by,
                'action' => 'rfid.tag_registered',
                'table_name' => 'customer_container_rfid_tags',
                'record_id' => $tag->id,
            ]);

            // Emit Realtime event
            try {
                app(RealtimeEventService::class)->emit('rfid', $tag->id, 'RFID_TAG_REGISTERED', 'active');
            } catch (\Throwable) {
                // Silently continue if realtime service is unavailable
            }

            return [
                'success' => true,
                'message' => 'RFID tag registered successfully.',
                'tag' => [
                    'id' => $tag->id,
                    'uid' => $tag->uid,
                    'registered_at' => $tag->registered_at->toIso8601String(),
                ],
                'container' => [
                    'id' => $container->id,
                    'container_code' => $container->container_code,
                    'container_type' => $container->container_type,
                ],
                'customer' => [
                    'id' => $container->customer?->id,
                    'name' => $container->customer?->user?->name,
                ],
                'transaction_id' => $transaction->id,
            ];
        });
    }

    /**
     * Atomically replace an RFID tag on an existing container.
     */
    public function replaceTag(string $oldTagId, string $rawNewUid, string $reason, User $admin): array
    {
        $newUid = strtoupper(trim(preg_replace('/[\s:]+/', '', $rawNewUid)));

        if (empty($newUid)) {
            throw ValidationException::withMessages([
                'new_uid' => ['Please enter a valid new RFID UID.'],
            ]);
        }

        return DB::transaction(function () use ($oldTagId, $newUid, $reason, $admin) {
            /** @var CustomerContainerRfidTag $oldTag */
            $oldTag = CustomerContainerRfidTag::where('id', $oldTagId)->lockForUpdate()->firstOrFail();

            if ($oldTag->uid === $newUid) {
                throw ValidationException::withMessages([
                    'new_uid' => ['The new UID must be different from the current UID.'],
                ]);
            }

            // Check if new UID already active
            $duplicateCustomer = CustomerContainerRfidTag::where('uid', $newUid)
                ->where('status', 'active')
                ->exists();
            $duplicateStation = RfidTag::where('tag_uid', $newUid)->exists();

            if ($duplicateCustomer || $duplicateStation) {
                throw ValidationException::withMessages([
                    'new_uid' => ["RFID UID {$newUid} is already registered to another gallon or container."],
                ]);
            }

            // Deactivate old tag
            $oldTag->status = 'replaced';
            $oldTag->deactivated_at = now();
            $oldTag->save();

            // Create new active tag
            $newTag = CustomerContainerRfidTag::create([
                'customer_container_id' => $oldTag->customer_container_id,
                'uid' => $newUid,
                'status' => 'active',
                'registered_at' => now(),
            ]);

            // Transaction
            RfidTransaction::create([
                'customer_container_id' => $oldTag->customer_container_id,
                'customer_container_rfid_tag_id' => $newTag->id,
                'action' => 'tag_replacement',
                'transaction_type' => 'TAG_REPLACEMENT',
                'previous_status' => 'active',
                'new_status' => 'replaced',
                'scan_result' => 'accepted',
                'message' => "Replaced RFID UID {$oldTag->uid} with {$newUid}. Reason: {$reason}",
                'scanned_by' => $admin->id,
                'scanned_at' => now(),
            ]);

            // Audit
            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'rfid.tag_replaced',
                'table_name' => 'customer_container_rfid_tags',
                'record_id' => $newTag->id,
            ]);

            try {
                app(RealtimeEventService::class)->emit('rfid', $newTag->id, 'RFID_TAG_REPLACED', 'active');
            } catch (\Throwable) {}

            return [
                'success' => true,
                'message' => 'RFID tag replaced successfully.',
                'old_tag' => $oldTag,
                'new_tag' => $newTag,
            ];
        });
    }

    /**
     * Deactivate an RFID tag.
     */
    public function deactivateTag(string $tagId, string $reason, User $admin): CustomerContainerRfidTag
    {
        return DB::transaction(function () use ($tagId, $reason, $admin) {
            $tag = CustomerContainerRfidTag::where('id', $tagId)->lockForUpdate()->firstOrFail();

            $tag->status = 'inactive';
            $tag->deactivated_at = now();
            $tag->save();

            RfidTransaction::create([
                'customer_container_id' => $tag->customer_container_id,
                'customer_container_rfid_tag_id' => $tag->id,
                'action' => 'tag_deactivation',
                'transaction_type' => 'TAG_DEACTIVATION',
                'previous_status' => 'active',
                'new_status' => 'inactive',
                'scan_result' => 'accepted',
                'message' => "Deactivated RFID UID {$tag->uid}. Reason: {$reason}",
                'scanned_by' => $admin->id,
                'scanned_at' => now(),
            ]);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'rfid.tag_deactivated',
                'table_name' => 'customer_container_rfid_tags',
                'record_id' => $tag->id,
            ]);

            try {
                app(RealtimeEventService::class)->emit('rfid', $tag->id, 'RFID_TAG_DEACTIVATED', 'inactive');
            } catch (\Throwable) {}

            return $tag;
        });
    }
}

