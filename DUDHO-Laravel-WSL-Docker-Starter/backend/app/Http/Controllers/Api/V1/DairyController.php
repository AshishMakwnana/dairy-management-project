<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDairyRequest;
use App\Http\Requests\Api\V1\UpdateDairyRequest;
use App\Http\Resources\DairyDetailResource;
use App\Http\Resources\DairyResource;
use App\Models\Dairy;
use App\Models\User;
use App\Services\DairyOnboardingService;
use App\Services\DairyProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DairyController extends Controller
{
    public function index(Request $request, DairyOnboardingService $dairyOnboardingService): JsonResponse
    {
        Gate::authorize('viewAny', Dairy::class);
        $user = $this->authenticatedUser($request);
        $dairies = $dairyOnboardingService->listFor($user);

        return response()->json([
            'success' => true,
            'message' => 'Dairies retrieved successfully.',
            'data' => DairyResource::collection($dairies)->resolve($request),
            'meta' => ['total' => $dairies->count()],
        ]);
    }

    public function store(
        StoreDairyRequest $request,
        DairyOnboardingService $dairyOnboardingService,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        ['dairy' => $dairy, 'created' => $created] = $dairyOnboardingService->create(
            $user,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Dairy created successfully.',
            'data' => (new DairyResource($dairy))->resolve($request),
            'meta' => [],
        ], $created ? 201 : 200);
    }

    public function show(Request $request, Dairy $dairy): JsonResponse
    {
        Gate::authorize('view', $dairy);

        return response()->json([
            'success' => true,
            'message' => 'Dairy retrieved successfully.',
            'data' => (new DairyDetailResource($dairy->load('creator')))->resolve($request),
            'meta' => [],
        ]);
    }

    public function update(
        UpdateDairyRequest $request,
        Dairy $dairy,
        DairyProfileService $dairyProfileService,
    ): JsonResponse {
        $updatedDairy = $dairyProfileService->update($dairy, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Dairy updated successfully.',
            'data' => (new DairyDetailResource($updatedDairy))->resolve($request),
            'meta' => [],
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
