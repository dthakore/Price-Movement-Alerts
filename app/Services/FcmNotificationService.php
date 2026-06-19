<?php

namespace App\Services;

use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmNotificationService
{
    public function __construct(private Messaging $messaging) {}

    /**
     * Send a push notification to all FCM tokens registered for a user.
     * Stale / invalid tokens are automatically removed from the DB.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = UserFcmToken::where('user_id', $userId)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data));

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                foreach ($report->failures()->getItems() as $failure) {
                    $staleToken = $failure->target()->value();
                    $errorCode  = $failure->error()->errors()[0] ?? '';

                    Log::channel('price_alert')->warning("FCM send failed for token [{$staleToken}]: {$errorCode}");

                    // Remove tokens FCM reports as permanently invalid
                    if ($this->isStaleToken($errorCode)) {
                        UserFcmToken::where('fcm_token', $staleToken)->delete();
                        Log::channel('price_alert')->info("Deleted stale FCM token [{$staleToken}] for user {$userId}");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('price_alert')->error("FCM multicast failed for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send to a single token directly.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data));

        try {
            $this->messaging->send($message);
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            UserFcmToken::where('fcm_token', $token)->delete();
            Log::channel('price_alert')->info("Deleted not-found FCM token [{$token}]");
        } catch (\Throwable $e) {
            Log::channel('price_alert')->error("FCM send failed for token [{$token}]: " . $e->getMessage());
        }
    }

    private function isStaleToken(string $errorCode): bool
    {
        return in_array($errorCode, [
            'registration-token-not-registered',
            'invalid-argument',
            'invalid-registration-token',
            'not-registered',
        ]);
    }
}
