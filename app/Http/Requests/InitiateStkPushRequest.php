<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateStkPushRequest extends FormRequest
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
            'phone_number' => ['required', 'string', 'max:20'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'account_reference' => ['nullable', 'string', 'max:12'],
            'description' => ['nullable', 'string', 'max:13'],
        ];
    }
}
