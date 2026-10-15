<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
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
            'channel' => ['sometimes', 'string', 'in:sms,whatsapp'],
            'locale' => ['sometimes', 'string', 'in:en,hi'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone must be an Indian mobile number in +91 E.164 format.',
        ];
    }
}
