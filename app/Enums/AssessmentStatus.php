<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case InsufficientData = 'insufficient_data';
    case ManualReview = 'manual_review';
    case Failed = 'failed';
}
