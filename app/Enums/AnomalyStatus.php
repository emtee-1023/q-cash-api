<?php

namespace App\Enums;

enum AnomalyStatus: string
{
    case Normal = 'normal';
    case Flagged = 'flagged';
    case RequiresReview = 'requires_review';
}
