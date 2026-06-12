<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return [
        'app' => config('app.name'),
        'backend' => 'Deneb by Nuwebs',
    ];
});

Route::middleware('guest')->group(function () {
    Route::post('/signup', [UserController::class, 'signUp'])->name('user.signup');

    // Authentication routes (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth token management routes
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/tokens/refresh', [AuthController::class, 'refreshToken'])->name('auth.tokens.refresh');
    Route::post('/auth/tokens/api', [AuthController::class, 'createApiKey'])->name('auth.api-keys.create');
    Route::get('/auth/tokens', [AuthController::class, 'tokens'])->name('auth.tokens.index');
    Route::delete('/auth/tokens/{tokenId}', [AuthController::class, 'revokeToken'])->name('auth.tokens.revoke');
    Route::delete('/auth/tokens', [AuthController::class, 'revokeAllTokens'])->name('auth.tokens.revoke-all');
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification'])->name('auth.resend-verification');

    Route::get('/me', [UserController::class, 'showMe'])->name('me');
    Route::get('/users/roles-below', [UserController::class, 'rolesBelow'])->name('users.rolesBelow');
    Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.updatePassword');
    Route::apiResource('/users', UserController::class);

    Route::apiResource('/roles', RoleController::class);
});
