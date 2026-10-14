<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'DUDHO API is available.',
            'data' => [
                'service' => 'dudho-api',
                'status' => 'ok',
                'version' => 'v1',
            ],
            'meta' => [],
        ]);
    });
});
