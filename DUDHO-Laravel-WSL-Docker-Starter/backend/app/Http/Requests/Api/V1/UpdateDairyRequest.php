<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Dairy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDairyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dairy = $this->route('dairy');

        return $dairy instanceof Dairy
            && ($this->user()?->can('update', $dairy) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', 'not_regex:/^\s*$/u'],
            'location' => ['sometimes', 'required', 'array:city,state,country,postal_code'],
            'location.city' => ['sometimes', 'required', 'string', 'max:150', 'not_regex:/^\s*$/u'],
            'location.state' => ['sometimes', 'required', 'string', 'max:150', 'not_regex:/^\s*$/u'],
            'location.country' => ['sometimes', 'required', 'string', 'size:2', Rule::in(['IN'])],
            'location.postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name', 'location'];
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, 'This field cannot be updated.');
                }
            }

            if ($this->all() === []) {
                $validator->errors()->add('name', 'At least one dairy profile field is required.');
            }

            $location = $this->input('location');
            if (is_array($location) && $location === []) {
                $validator->errors()->add('location', 'At least one location field is required.');
            }
        });
    }
}
