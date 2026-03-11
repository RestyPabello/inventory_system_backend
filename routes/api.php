<?php

use App\Http\Controllers\Items\ItemController;
use App\Http\Controllers\Items\ItemImageController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Permissions\PermissionController;
use App\Http\Controllers\Units\UnitController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:api')->group( function () {
        Route::middleware(['role:admin'])->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('permissions', PermissionController::class);
        });
        
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('items', ItemController::class);
        Route::apiResource('units', UnitController::class);

       Route::post('items/images/upload', [ItemImageController::class, 'store']);

        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });

    Route::prefix('users')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
});