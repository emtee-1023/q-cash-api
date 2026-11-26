<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MpesaRepaymentRequest extends FormRequest
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
        ];
    }
}
