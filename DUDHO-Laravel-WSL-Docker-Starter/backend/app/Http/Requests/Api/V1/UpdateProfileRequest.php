<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DairyUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $userId = $this->user()->getAuthIdentifier();

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:150', 'not_regex:/^\s*$/u'],
            'preferred_language' => ['sometimes', 'required', 'string', Rule::in(['en', 'hi'])],
            'default_dairy_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('dairies', 'uuid')->whereIn(
                    'id',
                    DairyUser::query()
                        ->select('dairy_id')
                        ->where('user_id', $userId)
                        ->where('status', 'active'),
                ),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedFields = ['full_name', 'preferred_language', 'default_dairy_id'];

                foreach (array_diff(array_keys($this->all()), $allowedFields) as $field) {
                    $validator->errors()->add($field, 'This field is not supported.');
                }

                if (array_intersect(array_keys($this->all()), $allowedFields) === []) {
                    $validator->errors()->add(
                        'profile',
                        'At least one supported profile field must be provided.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.not_regex' => 'The full name field must not be blank.',
            'default_dairy_id.exists' => 'The selected dairy must have an active membership.',
        ];
    }
}
