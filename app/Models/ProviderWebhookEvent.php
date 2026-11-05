<?php

namespace App\Models;

use App\Enums\WebhookProcessingStatus;
use Database\Factories\ProviderWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'external_event_id',
    'event_type',
    'payload',
    'processing_status',
    'processing_error',
    'received_at',
    'processed_at',
])]
class ProviderWebhookEvent extends Model
{
    /** @use HasFactory<ProviderWebhookEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processing_status' => WebhookProcessingStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
