<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockBatchController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
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
    Route::apiResource('/material-categories', MaterialCategoryController::class);
    Route::apiResource('/materials', MaterialController::class);
    Route::apiResource('/suppliers', SupplierController::class);
    Route::apiResource('/product-categories', ProductCategoryController::class);
    Route::apiResource('/products', ProductController::class);
    Route::apiResource('/purchases', PurchaseController::class);
    Route::apiResource('/sales', SaleController::class)->only(['index', 'store', 'show']);
    Route::apiResource('/stock-batches', StockBatchController::class)->only(['index', 'show']);
    Route::apiResource('/stock-movements', StockMovementController::class)->only(['index', 'store', 'show']);
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
    Route::get('/inventory/materials', [InventoryController::class, 'materials'])->name('inventory.materials');
    Route::get('/inventory/products', [InventoryController::class, 'products'])->name('inventory.products');
});
