<?php

namespace App\Http\Requests;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBusinessRequest extends FormRequest
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
        /** @var Business|null $business */
        $business = $this->route('business');

        return [
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('business_types', 'id')->where('is_active', true),
            ],
            'has_physical_location' => ['sometimes', 'boolean'],
            'county_id' => ['sometimes', 'nullable', 'integer', 'exists:counties,id'],
            'sub_county_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('sub_counties', 'id')->where(
                    fn ($query) => $query->where('county_id', $this->input('county_id', $business?->county_id)),
                ),
            ],
            'ward_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('wards', 'id')->where(
                    fn ($query) => $query->where('sub_county_id', $this->input('sub_county_id', $business?->sub_county_id)),
                ),
            ],
            'physical_address' => ['sometimes', 'nullable', 'string'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny($this->profileFields())) {
                    $validator->errors()->add('business_name', 'At least one business field is required.');
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function profileFields(): array
    {
        return [
            'business_name',
            'business_type_id',
            'has_physical_location',
            'county_id',
            'sub_county_id',
            'ward_id',
            'physical_address',
            'phone_number',
            'email',
        ];
    }
}
