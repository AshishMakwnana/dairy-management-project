<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\UserProfileService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request)->load('defaultDairy');

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => (new ProfileResource($user))->resolve($request),
            'meta' => [],
        ]);
    }

    public function update(
        UpdateProfileRequest $request,
        UserProfileService $userProfileService,
    ): JsonResponse {
        $user = $userProfileService->update(
            $this->authenticatedUser($request),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => (new ProfileResource($user))->resolve($request),
            'meta' => [],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->authenticatedUser($request)->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully.',
            'data' => null,
            'meta' => [],
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
