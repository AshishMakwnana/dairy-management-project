<?php

namespace App\Http\Resources;

use App\Models\Dairy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dairy */
class DairyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'state' => $this->onboarding_completed_at === null ? 'draft' : 'complete',
            'onboarding_step' => $this->onboarding_completed_at === null ? 'basic_profile' : null,
            'location' => [
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country_code,
                'postal_code' => $this->postal_code,
            ],
            'owner' => [
                'id' => $this->creator->uuid,
                'full_name' => $this->creator->name,
            ],
        ];
    }
}
