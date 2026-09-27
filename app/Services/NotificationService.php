<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected \Kreait\Firebase\Contract\Messaging $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(config('firebase.credentials'))
            ->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->fcmTokens()->pluck('token')->all();

        if (empty($tokens)) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data, $user);
    }

    protected function sendToTokens(array $tokens, string $title, string $body, array $data, ?User $user = null): void
    {
        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
            $this->pruneInvalidTokens($report, $user);
        } catch (\Throwable $e) {
            Log::error('FCM send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove tokens Firebase reports as invalid/unregistered so we stop retrying them.
     */
    protected function pruneInvalidTokens(MulticastSendReport $report, ?User $user): void
    {
        if (! $user) {
            return;
        }

        foreach ($report->invalidTokens() as $invalidToken) {
            $user->fcmTokens()->where('token', $invalidToken)->delete();
        }
    }
}
