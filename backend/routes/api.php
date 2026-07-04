<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EzraController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\V2\CompareGroupController;
use App\Http\Controllers\Api\V2\PassageController;
use App\Http\Controllers\Api\V2\ExplanationController;
use App\Http\Controllers\Api\V2\EzraV2Controller;
use App\Http\Controllers\Api\V2\ProgressV2Controller;
use App\Http\Controllers\Api\V2\StreamPlanController;
use Illuminate\Support\Facades\Route;

// --- Público (lectura), con idioma resuelto por el middleware SetLocale ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle']);

Route::get('/translations', [TranslationController::class, 'index']);
Route::get('/routes', [RouteController::class, 'index']);
Route::get('/routes/{route:slug}', [RouteController::class, 'show']);
Route::get('/events/{event:slug}', [EventController::class, 'show']);
Route::get('/characters/{character:slug}', [CharacterController::class, 'show']);

// --- Autenticado (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/me/progress', [ProgressController::class, 'show']);
    Route::post('/me/progress/complete', [ProgressController::class, 'complete']);

    Route::post('/events/{event:slug}/ask', [EzraController::class, 'ask']);
});

// ─── Readings (bible text — public, no auth required) ───────────────────────
Route::prefix('readings')->group(function () {
    Route::get('/books',                              [ReadingController::class, 'books']);
    Route::get('/book/{osisCode}/chapter/{number}',   [ReadingController::class, 'chapter']);
    Route::get('/{blockId}',                          [ReadingController::class, 'show']);
});

// ─── API v2 ──────────────────────────────────────────────────────────────────
// Public read endpoints
Route::prefix('v2')->group(function () {
    Route::get('/stream-plans/{id}',                        [StreamPlanController::class, 'show']);
    Route::get('/stream-plans/{id}/chronological',          [StreamPlanController::class, 'chronological']);
    Route::get('/stream-plans/{planId}/nodes/{nodeId}',     [StreamPlanController::class, 'showNode']);
    Route::get('/compare-groups/{id}',                      [CompareGroupController::class, 'show']);
    Route::get('/explanations/{crsId}',                     [ExplanationController::class, 'show']);
    Route::get('/passages/block/{blockId}',                 [PassageController::class, 'showForBlock']);
});

// Authenticated v2 endpoints
Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::post('/progress/blocks/{blockId}',   [ProgressV2Controller::class, 'markBlock']);
    Route::post('/progress/nodes/{nodeId}',     [ProgressV2Controller::class, 'markNodeState']);
    Route::get('/progress/summary',             [ProgressV2Controller::class, 'summary']);
    Route::post('/ezra/answer',                 [EzraV2Controller::class, 'answer']);
});
