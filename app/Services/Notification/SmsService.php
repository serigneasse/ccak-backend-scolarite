<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $apiUrl = 'https://api.ccak.edu.sn/api/notifications/rabbitmq/bulk-sms';
    protected string $senderAddress = 'tel:+221787841965';
    protected string $senderName = 'UCAK';

    /**
     * Send SMS notification via RabbitMQ API
     */
    public function send(
        string|array $recipients,
        string $message,
        ?string $senderAddress = null,
        ?string $senderName = null
    ): void {
        // Prepare recipients array
        $recipientsArray = is_array($recipients) ? $recipients : [$recipients];

        // Format phone numbers to international format if needed
        $recipientsArray = array_map(function ($phone) {
            // Add +221 if not present and phone starts with 7
            if (!str_starts_with($phone, '+') && str_starts_with($phone, '7')) {
                return '+221' . $phone;
            }
            return $phone;
        }, $recipientsArray);

        $payload = [
            'recipients' => $recipientsArray,
            'message' => $message,
            'senderAddress' => $senderAddress ?? $this->senderAddress,
            'senderName' => $senderName ?? $this->senderName,
        ];

        try {
            $response = Http::post($this->apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('SMS API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload,
                ]);
            } else {
                Log::info('SMS sent successfully', [
                    'recipients' => $recipientsArray,
                    'message' => $message,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}
