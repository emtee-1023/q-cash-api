<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessment_id' => ['required', 'integer', 'exists:era_assessments,id'],
            'fee_amount_minor' => ['nullable', 'integer', 'min:0'],
            'expires_in_days' => ['nullable', 'integer', 'min:1'],
            'fallback_terms' => ['nullable', 'array'],
        ];
    }
}
