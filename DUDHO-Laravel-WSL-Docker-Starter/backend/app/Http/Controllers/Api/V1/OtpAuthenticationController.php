<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Services\OtpAuthenticationService;
use Illuminate\Http\JsonResponse;

class OtpAuthenticationController extends Controller
{
    public function __construct(private readonly OtpAuthenticationService $otpAuthenticationService) {}

    public function send(SendOtpRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => $this->otpAuthenticationService->send($request->validated(), $request),
            'meta' => [],
        ]);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Signed in successfully.',
            'data' => $this->otpAuthenticationService->verify($request->validated(), $request),
            'meta' => [],
        ]);
    }
}
