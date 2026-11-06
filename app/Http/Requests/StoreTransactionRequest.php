<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'provider' => ['required', 'string', 'max:50'],
            'provider_transaction_id' => ['required', 'string', 'max:150'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', Rule::in(['inflow', 'outflow'])],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'transaction_time' => ['required', 'date'],
            'provider_status' => ['nullable', 'string', 'max:30'],
            'counterparty_reference' => ['nullable', 'string', 'max:150'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'raw_payload' => ['nullable', 'array'],
        ];
    }
}
