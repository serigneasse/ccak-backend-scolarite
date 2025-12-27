<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendSmsNotificationJob;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Send notification to user(s)
     */
    public function send(
        array|int $userIds,
        string $title,
        string $message,
        string $type,
        array $channels = ['in_app'],
        array $metadata = []
    ): Collection {
        $userIds = is_array($userIds) ? $userIds : [$userIds];
        $notifications = collect();

        foreach ($userIds as $userId) {
            foreach ($channels as $channel) {
                $notification = $this->createNotification(
                    $userId,
                    $title,
                    $message,
                    $type,
                    $channel,
                    $metadata
                );

                $notifications->push($notification);

                // Queue delivery based on channel
                $this->queueDelivery($notification);
            }
        }

        return $notifications;
    }

    /**
     * Create notification record
     */
    protected function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type,
        string $channel,
        array $metadata
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'channel' => $channel,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Queue notification delivery
     */
    protected function queueDelivery(Notification $notification): void
    {
        match ($notification->channel) {
            Notification::CHANNEL_EMAIL => SendEmailNotificationJob::dispatch($notification),
            Notification::CHANNEL_SMS => SendSmsNotificationJob::dispatch($notification),
            Notification::CHANNEL_IN_APP => null, // Already saved in DB
            default => null,
        };
    }

    /**
     * Send grade published notification
     */
    public function sendGradePublished(User $user, $grade): void
    {
        $this->send(
            $user->id,
            'Nouvelle note disponible',
            "Votre note pour {$grade->subject->name} a été publiée.",
            Notification::TYPE_GRADE_PUBLISHED,
            ['in_app', 'email'],
            [
                'grade_id' => $grade->id,
                'subject' => $grade->subject->name,
                'score' => $grade->score,
            ]
        );
    }

    /**
     * Send enrollment confirmed notification
     */
    public function sendEnrollmentConfirmed(User $user, $enrollment): void
    {
        $this->send(
            $user->id,
            'Inscription confirmée',
            "Votre inscription à {$enrollment->course->name} a été confirmée.",
            Notification::TYPE_ENROLLMENT_CONFIRMED,
            ['in_app', 'email'],
            [
                'enrollment_id' => $enrollment->id,
                'course_name' => $enrollment->course->name,
            ]
        );
    }

    /**
     * Send document ready notification
     */
    public function sendDocumentReady(User $user, $document): void
    {
        $this->send(
            $user->id,
            'Document disponible',
            "Le document {$document->name} est maintenant disponible.",
            Notification::TYPE_DOCUMENT_READY,
            ['in_app'],
            [
                'document_id' => $document->id,
                'document_name' => $document->name,
                'download_url' => $document->download_url,
            ]
        );
    }

    /**
     * Send welcome notification
     */
    public function sendWelcome(User $user): void
    {
        $this->send(
            $user->id,
            'Bienvenue !',
            "Bienvenue sur notre plateforme, {$user->name}.",
            Notification::TYPE_WELCOME,
            ['in_app', 'email'],
            []
        );
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordReset(User $user, string $token): void
    {
        $this->send(
            $user->id,
            'Réinitialisation du mot de passe',
            'Vous avez demandé une réinitialisation de mot de passe.',
            Notification::TYPE_PASSWORD_RESET,
            ['email'],
            [
                'reset_token' => $token,
                'reset_url' => url("/reset-password?token={$token}"),
            ]
        );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): bool
    {
        $notification = Notification::findOrFail($notificationId);
        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->count();
    }
}