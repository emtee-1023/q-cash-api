<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Validation\Rule;
use Markt\LaravelAuth\Http\Requests\RegisterRequest as BaseRegisterRequest;

class RegisterRequest extends BaseRegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'id_type' => ['required', Rule::enum(IdentificationType::class)],
            'identification_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'identification_number')
                    ->where(fn ($query) => $query->where('id_type', $this->input('id_type'))),
            ],
            'date_of_birth' => ['required', 'date_format:dmY', 'before:today'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $email = $this->input('email');

            $this->merge([
                'email' => filled($email) ? strtolower(trim((string) $email)) : null,
            ]);
        }
    }
}
