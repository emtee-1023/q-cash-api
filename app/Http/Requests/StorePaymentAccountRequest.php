<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentAccountRequest extends FormRequest
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
            'channel_type' => ['required', 'string', 'max:50'],
            'account_reference' => ['nullable', 'string', 'max:150'],
            'account_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
