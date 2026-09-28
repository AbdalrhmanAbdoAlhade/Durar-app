<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationService
{
    protected ?Messaging $messaging = null;

    /**
     * Built lazily so the app (and tests) don't crash when Firebase
     * credentials are not configured yet.
     */
    protected function messaging(): ?Messaging
    {
        if ($this->messaging) {
            return $this->messaging;
        }

        $credentials = config('firebase.credentials');

        if (! $credentials || ! is_file($credentials)) {
            Log::warning('Firebase credentials file not found, skipping push notification', ['path' => $credentials]);

            return null;
        }

        return $this->messaging = (new Factory)
            ->withServiceAccount($credentials)
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
        $messaging = $this->messaging();

        if (! $messaging) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        try {
            $report = $messaging->sendMulticast($message, $tokens);
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
