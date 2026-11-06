<?php

namespace Database\Factories;

use App\Enums\WebhookProcessingStatus;
use App\Models\ProviderWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderWebhookEvent>
 */
class ProviderWebhookEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'Safaricom',
            'external_event_id' => fake()->unique()->uuid(),
            'event_type' => 'payment_received',
            'payload' => ['amount_minor' => 10000],
            'processing_status' => WebhookProcessingStatus::Pending->value,
            'processing_error' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }
}
