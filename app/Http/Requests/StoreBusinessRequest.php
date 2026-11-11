<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:255'],
            'business_type_id' => [
                'required',
                'integer',
                Rule::exists('business_types', 'id')->where('is_active', true),
            ],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'has_physical_location' => ['sometimes', 'boolean'],
            'county_id' => ['nullable', 'integer', 'exists:counties,id'],
            'sub_county_id' => [
                'nullable',
                'integer',
                Rule::exists('sub_counties', 'id')
                    ->where(fn ($query) => $query->where('county_id', $this->input('county_id'))),
            ],
            'ward_id' => [
                'nullable',
                'integer',
                Rule::exists('wards', 'id')
                    ->where(fn ($query) => $query->where('sub_county_id', $this->input('sub_county_id'))),
            ],
            'physical_address' => ['nullable', 'string'],
            'operating_since' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('has_physical_location')) {
            $this->merge(['has_physical_location' => true]);
        }
    }
}
