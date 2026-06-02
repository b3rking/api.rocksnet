<?php

use App\Http\Controllers\AgentStockController;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Currency;
use App\Models\Role;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (Requires a valid token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/users/{user}', [AuthController::class, 'update']);
    Route::delete('/users/{user}', [AuthController::class, 'delete']);
    Route::get('currencies', [AppController::class, 'currencies']);
    Route::get('roles', [AppController::class, 'roles']);
    Route::apiResource('profils', ProfilController::class);
    Route::get('list/agents', [AppController::class, 'listAgent']);
    Route::get('list/users', [AppController::class, 'users']);
    Route::get('list/super-agents', [AppController::class, 'listSuperAgent']);
    Route::get('list/admins', [AppController::class, 'listAdmin']);
    Route::get('stock', [AgentStockController::class, 'index']);
    Route::post('stock/attribute', [AgentStockController::class, 'attributeTicket']);
    Route::post('stock/sale', [AgentStockController::class, 'saleTicket']);
    Route::get('stock/history', [AgentStockController::class, 'saleHistory']);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('clients', ClientController::class);
    Route::get('dashboard', [AppController::class, 'dashboard_data']);
});
