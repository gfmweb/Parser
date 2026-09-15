<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ParseJobController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('organizations', OrganizationController::class)
        ->only(['index', 'store', 'show', 'destroy']);
    Route::post('organizations/{organization}/parse', [OrganizationController::class, 'triggerParse']);
    Route::get('organizations/{organization}/reviews', [ReviewController::class, 'index']);
    Route::get('organizations/{organization}/snapshots', [SnapshotController::class, 'index']);
    Route::get('organizations/{organization}/parse-job', [ParseJobController::class, 'show']);
});
