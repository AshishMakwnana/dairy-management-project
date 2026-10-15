<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Dairy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDairyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Dairy::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'not_regex:/^\s*$/u'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'location' => ['required', 'array:city,state,country,postal_code'],
            'location.city' => ['required', 'string', 'max:150'],
            'location.state' => ['required', 'string', 'max:150'],
            'location.country' => ['required', 'string', 'size:2', Rule::in(['IN'])],
            'location.postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'client_uuid' => ['required', 'uuid'],
            'owner_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.not_regex' => 'The name field must not be blank.',
            'location.country.in' => 'The location country must be IN for the initial rollout.',
            'owner_id.prohibited' => 'The dairy owner is determined by the authenticated user.',
            'user_id.prohibited' => 'The dairy owner is determined by the authenticated user.',
            'created_by_user_id.prohibited' => 'The dairy creator is determined by the authenticated user.',
            'role.prohibited' => 'The dairy role is assigned by the server.',
        ];
    }
}
