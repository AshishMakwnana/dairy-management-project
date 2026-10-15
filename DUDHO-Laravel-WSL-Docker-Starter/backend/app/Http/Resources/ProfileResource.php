<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'full_name' => $this->name,
            'phone' => $this->mobile === null
                ? null
                : $this->mobile_country_code.$this->mobile,
            'preferred_language' => $this->preferred_language,
            'default_dairy_id' => $this->defaultDairy?->uuid,
        ];
    }
}
