<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Markt\LaravelAuth\Contracts\SmsSender;
use RuntimeException;

class AfricaTalkingSmsSender implements SmsSender
{
    public function send(string $phoneNumber, string $message): void
    {
        $endpoint = config('services.africastalking.endpoint');
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        if (blank($endpoint) || blank($username) || blank($apiKey)) {
            throw new RuntimeException('Africa\'s Talking SMS is not configured.');
        }

        $recipientPhoneNumber = '+'.PhoneNumber::normalize($phoneNumber);

        $payload = [
            'username' => $username,
            'message' => $message,
            'to' => $recipientPhoneNumber,
        ];

        $response = Http::asForm()
            ->withHeaders([
                'apiKey' => $apiKey,
            ])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Africa\'s Talking SMS request failed with status '.$response->status().'.'
            );
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            throw new RuntimeException(
                'Invalid response received from Africa\'s Talking.'
            );
        }

        $recipient = $xml
            ->SMSMessageData
            ->Recipients
            ->Recipient
            ?? null;

        if ($recipient === null) {
            throw new RuntimeException(
                'Africa\'s Talking returned no recipient information.'
            );
        }

        if ((string) $recipient->status !== 'Success') {
            throw new RuntimeException(
                'Africa\'s Talking reported that the SMS was not sent successfully.'
            );
        }
    }
}
