<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\HealthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private readonly HealthService $healthService) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'DUDHO API is available.',
            'data' => $this->healthService->status(),
            'meta' => [],
        ]);
    }
}
