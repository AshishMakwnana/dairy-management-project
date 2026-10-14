<?php

namespace App\Services;

use App\Contracts\HealthService;

class SystemHealthService implements HealthService
{
    /**
     * @return array{service: string, status: string, version: string}
     */
    public function status(): array
    {
        return [
            'service' => 'dudho-api',
            'status' => 'ok',
            'version' => 'v1',
        ];
    }
}
