<?php

namespace App\Enums;

enum WebhookProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
