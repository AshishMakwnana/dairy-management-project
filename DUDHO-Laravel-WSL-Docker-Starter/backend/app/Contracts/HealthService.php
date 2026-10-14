<?php

namespace App\Contracts;

interface HealthService
{
    /**
     * @return array{service: string, status: string, version: string}
     */
    public function status(): array;
}
