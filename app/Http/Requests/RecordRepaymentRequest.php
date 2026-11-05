<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordRepaymentRequest extends FormRequest
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
            'collection_method' => ['required', Rule::in(['revenue_deduction', 'manual_payment', 'fallback'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:150'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:150'],
            'collected_at' => ['nullable', 'date'],
            'source_transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.advance_id' => ['required', 'integer', 'exists:advances,id'],
            'allocations.*.amount_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
