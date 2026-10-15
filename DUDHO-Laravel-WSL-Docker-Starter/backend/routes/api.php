<?php

use App\Http\Controllers\Api\V1\DairyController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OtpAuthenticationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Models\Dairy;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::apiResource('dairies', DairyController::class)
        ->only(['index', 'store'])
        ->middleware('auth:sanctum');

    Route::bind('dairy', function (string $value): Dairy {
        $user = request()->user();

        abort_unless($user instanceof User && $user->status === 'active', 404);

        return Dairy::query()
            ->where('uuid', $value)
            ->where('status', 'active')
            ->whereHas('memberships', function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', 'active');
            })
            ->firstOrFail();
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/dairies/{dairy}', [DairyController::class, 'show'])
            ->name('api.v1.dairies.show');
        Route::patch('/dairies/{dairy}', [DairyController::class, 'update'])
            ->name('api.v1.dairies.update');
    });

    Route::prefix('auth/otp')->name('api.v1.auth.otp.')->group(function (): void {
        Route::post('/send', [OtpAuthenticationController::class, 'send'])->name('send');
        Route::post('/verify', [OtpAuthenticationController::class, 'verify'])->name('verify');
    });

    Route::prefix('auth')->name('api.v1.auth.')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
        Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');
    });
});
