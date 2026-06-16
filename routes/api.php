<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PitchController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\UsageController;
use App\Http\Controllers\Settings\UserAdminController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::post('/auth/request-code', [AuthController::class, 'requestCode'])->middleware('throttle:6,1');
Route::post('/auth/verify-code', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');

// Read-only public share view (sanitized one-pager).
Route::get('/share/{token}', [ShareController::class, 'show']);

// ---- Authenticated ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/meta', [MetaController::class, 'index']);
    Route::get('/people', [PeopleController::class, 'index']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project}/stage', [ProjectController::class, 'setStage']);
    Route::post('/projects/{project}/access', [ProjectController::class, 'attachUser']);
    Route::delete('/projects/{project}/access/{user}', [ProjectController::class, 'detachUser']);

    // Share token management (writers only, enforced in controller)
    Route::post('/projects/{project}/share', [ShareController::class, 'enable']);
    Route::post('/projects/{project}/share/regenerate', [ShareController::class, 'regenerate']);
    Route::delete('/projects/{project}/share', [ShareController::class, 'revoke']);

    // Nested resources
    Route::post('/projects/{project}/comments', [CommentController::class, 'store']);
    Route::delete('/projects/{project}/comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('/projects/{project}/checklist', [ChecklistController::class, 'toggle']);
    Route::post('/projects/{project}/links', [LinkController::class, 'store']);
    Route::delete('/projects/{project}/links/{link}', [LinkController::class, 'destroy']);

    // Files (S3 presigned flow)
    Route::post('/projects/{project}/files/presign', [FileController::class, 'presign']);
    Route::post('/projects/{project}/files', [FileController::class, 'store']);
    Route::delete('/projects/{project}/files/{file}', [FileController::class, 'destroy']);

    // Buyers
    Route::get('/buyers', [BuyerController::class, 'index']);
    Route::post('/buyers', [BuyerController::class, 'store']);
    Route::put('/buyers/{buyer}', [BuyerController::class, 'update']);
    Route::delete('/buyers/{buyer}', [BuyerController::class, 'destroy']);

    // Pitches
    Route::get('/pitches', [PitchController::class, 'index']);
    Route::post('/pitches', [PitchController::class, 'store']);
    Route::put('/pitches/{pitch}', [PitchController::class, 'update']);
    Route::delete('/pitches/{pitch}', [PitchController::class, 'destroy']);
    Route::post('/pitches/{pitch}/status', [PitchController::class, 'setStatus']);

    // Sicala AI
    Route::post('/ai/assistant', [AiController::class, 'assistant']);
    Route::post('/ai/autofill', [AiController::class, 'autofill']);
    Route::post('/ai/attachments/presign', [AiController::class, 'presignAttachment']);

    // ---- Admin-only Settings ----
    Route::middleware('admin')->prefix('settings')->group(function () {
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::post('/users', [UserAdminController::class, 'invite']);
        Route::put('/users/{user}', [UserAdminController::class, 'update']);
        Route::post('/users/{user}/deactivate', [UserAdminController::class, 'deactivate']);
        Route::delete('/users/{user}', [UserAdminController::class, 'destroy']);

        Route::get('/api-key', [ApiKeyController::class, 'show']);
        Route::put('/api-key', [ApiKeyController::class, 'update']);
        Route::post('/api-key/test', [ApiKeyController::class, 'test']);

        Route::get('/usage', [UsageController::class, 'index']);
    });
});
