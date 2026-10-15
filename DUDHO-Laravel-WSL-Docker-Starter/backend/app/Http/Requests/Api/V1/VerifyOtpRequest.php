<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+91[6-9]\d{9}$/'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'client_uuid' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone must be an Indian mobile number in +91 E.164 format.',
            'code.regex' => 'The code must contain 6 digits.',
        ];
    }
}
