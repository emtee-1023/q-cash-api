<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\UpdateBusinessRequest as BaseUpdateBusinessRequest;

class UpdateBusinessRequest extends BaseUpdateBusinessRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function profileFields(): array
    {
        return [...parent::profileFields(), 'registration_number'];
    }
}
