<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;

class NotificationService
{
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        // ------------------------------------------------------------
        // 1. Always save notification to database
        // ------------------------------------------------------------

        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;

        // The database has a unique key on (user_id, entity_type, entity_id).
        // Reuse the matching notification for every entity type, not only
        // orders. Otherwise, a second delivery update attempts a duplicate
        // insert and prevents the enclosing delivery transaction from
        // completing.
        if (filled($entityType) && filled($entityId)) {
            $notification = Notification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'is_read' => false,
                    'created_at' => now(),
                ]
            );
        } else {
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }

        try {
            app(RealtimeEventService::class)->emit(
                'notification',
                $notification->id,
                'created'
            );
        } catch (\Throwable $e) {
            // Database persistence is the source of truth. A realtime signal
            // must never prevent the notification itself from being delivered.
            report($e);
        }

        // ------------------------------------------------------------
        // 2. If user has no FCM token, stop here
        // ------------------------------------------------------------

        $credentials = config('services.firebase.credentials');
        if (!$user->fcm_token || blank($credentials)) {
            return;
        }

        // ------------------------------------------------------------
        // 3. Send Firebase push notification
        // ------------------------------------------------------------

        try {
            $messaging = (new Factory)
                ->withServiceAccount($credentials)
                ->createMessaging();

            $message = CloudMessage::new()
                ->withToken($user->fcm_token)
                ->withNotification(
                    FirebaseNotification::create(
                        $title,
                        $body
                    )
                )
                ->withData($data);

            $messaging->send($message);

        } catch (NotFound | InvalidArgument $e) {

            // --------------------------------------------------------
            // Token is stale or invalid
            // --------------------------------------------------------

            $user->update([
                'fcm_token' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
