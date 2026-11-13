<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
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
    public function createSession(
        string $containerId,
        string $customerId,
        string $deviceId,
        User $admin
    ): RfidRegistrationSession
    {
        return DB::transaction(function () use ($containerId, $customerId, $deviceId, $admin) {
            $customerIsEligible = Customer::whereKey($customerId)
                ->whereHas('user', function ($query) {
                    $query->where('role', 'customer')
                        ->where('status', 'active');
                })
                ->exists();

            if (!$customerIsEligible) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer is not active or eligible for RFID registration.'],
                ]);
            }

            $container = CustomerContainer::whereKey($containerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($container->customer_id !== $customerId) {
                throw ValidationException::withMessages([
                    'customer_container_id' => ['The selected container does not belong to the selected customer.'],
                ]);
            }

            if ($container->status !== 'active') {
                throw ValidationException::withMessages([
                    'customer_container_id' => ["Container is currently {$container->status} and cannot be assigned an RFID tag."],
                ]);
            }

            if ($container->activeTag()->exists()) {
                throw ValidationException::withMessages([
                    'customer_container_id' => ['This container already has an active RFID tag. Use Replace Tag instead.'],
                ]);
            }

            $device = RfidDevice::whereKey($deviceId)
                ->lockForUpdate()
                ->first();

            if (!$device || !$device->isOnline()) {
                throw ValidationException::withMessages([
                    'station_device_id' => ['The selected RFID scanner is offline or has not sent a recent authenticated heartbeat.'],
                ]);
            }

            if (RfidRegistrationSession::where('customer_container_id', $container->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->exists()) {
                throw ValidationException::withMessages([
                    'customer_container_id' => ['This container already has an active RFID registration session.'],
                ]);
            }

            if (RfidRegistrationSession::where('station_device_id', $device->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->exists()) {
                throw ValidationException::withMessages([
                    'station_device_id' => ['This RFID scanner is already handling another registration session.'],
                ]);
            }

            return RfidRegistrationSession::create([
                'customer_container_id' => $container->id,
                'station_device_id' => $device->id,
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
        return DB::transaction(function () use ($session, $admin) {
            $lockedSession = RfidRegistrationSession::whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status === 'pending') {
                $lockedSession->status = $lockedSession->expires_at->isPast()
                    ? 'expired'
                    : 'cancelled';
                $lockedSession->save();

                if ($lockedSession->status === 'cancelled') {
                    AuditLog::create([
                        'actor_id' => $admin->id,
                        'action' => 'rfid.session_cancelled',
                        'table_name' => 'rfid_registration_sessions',
                        'record_id' => $lockedSession->id,
                    ]);
                }
            }

            return $lockedSession;
        });
    }

    /**
     * Complete an RFID registration scan submitted by an ESP32 device.
     */
    public function completeRegistration(RfidDevice $device, string $sessionId, string $rawUid): array
    {
        $uid = strtoupper(trim(preg_replace('/[\s:]+/', '', $rawUid)));

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

            if (!$session->station_device_id || $session->station_device_id !== $device->id) {
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

            if (!$session->command_sent_at) {
                return [
                    'success' => false,
                    'error' => 'SESSION_NOT_DELIVERED',
                    'message' => 'The ESP32 has not received this registration command yet.',
                ];
            }

            $session->scan_received_at = now();
            $session->save();

            if (!preg_match('/\A(?:[A-F0-9]{8}|[A-F0-9]{10}|[A-F0-9]{12}|[A-F0-9]{14}|[A-F0-9]{16}|[A-F0-9]{18}|[A-F0-9]{20})\z/', $uid)) {
                return $this->rejectRegistration(
                    $session,
                    $device,
                    $uid,
                    'INVALID_UID',
                    'The scanned RFID UID must contain 4 to 10 bytes of hexadecimal data.'
                );
            }

            /** @var CustomerContainer $container */
            $container = CustomerContainer::where('id', $session->customer_container_id)
                ->lockForUpdate()
                ->first();

            if (!$container || $container->status !== 'active') {
                return $this->rejectRegistration($session, $device, $uid, 'CONTAINER_INACTIVE', 'Customer container is no longer active.');
            }

            // A UID remains reserved after deactivation or replacement.
            $existingCustomerTag = CustomerContainerRfidTag::where('uid', $uid)
                ->first();

            if ($existingCustomerTag) {
                return $this->rejectRegistration(
                    $session,
                    $device,
                    $uid,
                    'DUPLICATE_RFID_TAG',
                    "This RFID tag is already registered to container {$existingCustomerTag->container?->container_code}."
                );
            }

            // Check if UID is in station asset tags
            $existingStationTag = RfidTag::where('tag_uid', $uid)->first();
            if ($existingStationTag) {
                return $this->rejectRegistration(
                    $session,
                    $device,
                    $uid,
                    'DUPLICATE_RFID_TAG',
                    'This RFID tag is already assigned to a station gallon.'
                );
            }

            // Check if container already has an active tag
            $hasActiveTag = CustomerContainerRfidTag::where('customer_container_id', $container->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTag) {
                return $this->rejectRegistration(
                    $session,
                    $device,
                    $uid,
                    'CONTAINER_ALREADY_TAGGED',
                    'This container already has an active RFID tag assigned.'
                );
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
                'device' => [
                    'id' => $device->id,
                    'name' => $device->device_name,
                ],
                'transaction_id' => $transaction->id,
            ];
        });
    }

    private function rejectRegistration(
            RfidRegistrationSession $session,
            RfidDevice $device,
            string $uid,
            string $error,
            string $message
    ): array {
            $session->status = 'failed';
            $session->failure_code = $error;
            $session->failure_message = $message;
            $session->save();

            RfidTransaction::create([
                'customer_container_id' => $session->customer_container_id,
                'device_id' => $device->id,
                'action' => 'registration',
                'transaction_type' => 'REGISTRATION',
                'scan_result' => 'rejected',
                'message' => $message,
                'metadata' => ['uid' => $uid, 'error' => $error],
                'scanned_by' => $session->created_by,
                'scanned_at' => now(),
            ]);

            AuditLog::create([
                'actor_id' => $session->created_by,
                'action' => 'rfid.registration_scan_rejected',
                'table_name' => 'rfid_registration_sessions',
                'record_id' => $session->id,
            ]);

            return [
                'success' => false,
                'error' => $error,
                'message' => $message,
            ];
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

            // A UID remains reserved after deactivation or replacement.
            $duplicateCustomer = CustomerContainerRfidTag::where('uid', $newUid)
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

