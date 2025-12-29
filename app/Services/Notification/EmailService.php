<?php

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailService
{
    protected string $apiUrl;
    protected string $defaultFrom = 'noreply@ucak.sn';
    protected ?string $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('services.rabbitmq.email_api_url', 'https://api.ccak.edu.sn/api/notifications/rabbitmq/bulk-email');
        $this->apiToken = config('services.rabbitmq.api_token');
    }

    /**
     * Send email notification via RabbitMQ API
     */
    public function send(
        string $recipients,
        string $subject,
        string $message,
        string $template = 'notification',
        array $templateData = []
    ): void {
        // Prepare recipients array
        $recipientsArray = is_array($recipients) ? $recipients : [$recipients];

        // Prepare template data
        if (empty($templateData)) {
            $templateData = ['message' => $message];
        } elseif (!isset($templateData['message'])) {
            $templateData['message'] = $message;
        }

        $payload = [
            'recipients' => $recipientsArray,
            'from' => $this->defaultFrom,
            'subject' => $subject,
            'template' => $template,
            'templateData' => $templateData,
        ];

        try {
            $request = Http::timeout(30);

            // Add authorization header if token is configured
            if ($this->apiToken) {
                $request = $request->withToken($this->apiToken);
            }

            $response = $request->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('Email API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload,
                ]);
            } else {
                Log::info('Email sent via RabbitMQ API', [
                    'recipients' => $recipientsArray,
                    'subject' => $subject,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Send notification using RabbitMQ API
     */
    public function sendNotification(Notification $notification): void
    {
        $user = $notification->user;

        if (!$user || !$user->email) {
            return;
        }

        // Get user name from metadata or user model
        $userName = $notification->metadata['user.name'] ?? $user->name ?? 'Utilisateur';

        // Prepare template data from notification metadata
        $templateData = array_merge(
            $notification->metadata ?? [],
            [
                'title' => $notification->title,
                'message' => $notification->message,
                'user' => [
                    'name' => $userName,
                ],
            ]
        );

        $this->send(
            recipients: $user->email,
            subject: $notification->title,
            message: $notification->message,
            template: $notification->type,
            templateData: $templateData
        );
    }
}
