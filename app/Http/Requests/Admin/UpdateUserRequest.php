<?php

namespace App\Http\Requests\Admin;

use App\Enums\IdentificationType;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone_number')->ignore($user?->id),
            ],
            'id_type' => ['sometimes', 'nullable', Rule::enum(IdentificationType::class)],
            'identification_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny([
                    'name',
                    'email',
                    'phone_number',
                    'id_type',
                    'identification_number',
                    'date_of_birth',
                ])) {
                    $validator->errors()->add('name', 'At least one user field is required.');

                    return;
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->route('user');
                $idType = $this->input('id_type', $user->id_type?->value);
                $identificationNumber = $this->input('identification_number', $user->identification_number);

                if ($idType === null || $identificationNumber === null) {
                    return;
                }

                $exists = User::query()
                    ->where('id_type', $idType)
                    ->where('identification_number', $identificationNumber)
                    ->whereKeyNot($user->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'identification_number',
                        'The id type and identification number have already been taken.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $email = $this->input('email');

            $this->merge([
                'email' => filled($email) ? strtolower(trim((string) $email)) : null,
            ]);
        }

        if ($this->has('phone_number')) {
            $phoneNumber = $this->input('phone_number');

            $this->merge([
                'phone_number' => filled($phoneNumber) ? PhoneNumber::normalize((string) $phoneNumber) : null,
            ]);
        }
    }
}
