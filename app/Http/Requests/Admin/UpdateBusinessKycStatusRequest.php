<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessKycStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessKycStatusRequest extends FormRequest
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
            'kyc_status' => ['required', Rule::enum(BusinessKycStatus::class)],
        ];
    }
}
