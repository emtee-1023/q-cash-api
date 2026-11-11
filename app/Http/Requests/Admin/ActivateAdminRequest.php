<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActivateAdminRequest extends FormRequest
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
            'otp' => ['required', 'digits:'.config('laravel-auth.otp.length')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
