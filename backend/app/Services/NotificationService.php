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
    private $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(
                config('services.firebase.credentials')
            )
            ->createMessaging();
    }

    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        // ------------------------------------------------------------
        // 1. Always save notification to database
        // ------------------------------------------------------------

        Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
        ]);

        // ------------------------------------------------------------
        // 2. If user has no FCM token, stop here
        // ------------------------------------------------------------

        if (!$user->fcm_token) {
            return;
        }

        // ------------------------------------------------------------
        // 3. Send Firebase push notification
        // ------------------------------------------------------------

        try {
            $message = CloudMessage::new()
                ->withToken($user->fcm_token)
                ->withNotification(
                    FirebaseNotification::create(
                        $title,
                        $body
                    )
                )
                ->withData($data);

            $this->messaging->send($message);

        } catch (NotFound | InvalidArgument $e) {

            // --------------------------------------------------------
            // Token is stale or invalid
            // --------------------------------------------------------

            $user->update([
                'fcm_token' => null,
            ]);
        }
    }
}
