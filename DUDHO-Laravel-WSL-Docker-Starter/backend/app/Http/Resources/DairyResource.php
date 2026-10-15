<?php

namespace App\Http\Resources;

use App\Models\Dairy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dairy */
class DairyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'state' => $this->onboarding_completed_at === null ? 'draft' : 'complete',
            'location' => [
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country_code,
            ],
        ];
    }
}
