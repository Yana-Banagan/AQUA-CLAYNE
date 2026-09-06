<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Driver\DeliveryController;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::get('/auth/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum');

    // Products: authenticated customer, driver, admin, or staff
    Route::middleware([
        'auth:sanctum',
        'role:customer,driver,admin,staff'
    ])->get(
            '/products',
            [ProductController::class, 'index']
        );

    // Customer routes
    Route::middleware([
        'auth:sanctum',
        'role:customer'
    ])->group(function () {

        Route::get(
            '/addresses',
            [CustomerAddressController::class, 'index']
        );

        Route::post(
            '/addresses',
            [CustomerAddressController::class, 'store']
        );

        Route::put(
            '/addresses/{address}',
            [CustomerAddressController::class, 'update']
        );

        Route::delete(
            '/addresses/{address}',
            [CustomerAddressController::class, 'destroy']
        );

        Route::post(
            '/orders',
            [OrderController::class, 'store']
        );

        Route::get(
            '/orders',
            [OrderController::class, 'index']
        );

        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });
    // Driver routes
    Route::middleware([
        'auth:sanctum',
        'role:driver'
    ])->group(function () {

        Route::get(
            '/driver/deliveries',
            [DeliveryController::class, 'index']
        );

        Route::get(
            '/driver/deliveries/history',
            [DeliveryController::class, 'history']
        );

        Route::get(
            '/driver/deliveries/{delivery}',
            [DeliveryController::class, 'show']
        );

        Route::patch(
            '/driver/deliveries/{delivery}/status',
            [DeliveryController::class, 'updateStatus']
        );

        Route::post(
            '/driver/deliveries/{delivery}/proof',
            [DeliveryController::class, 'uploadProof']
        );

        Route::post('/driver/deliveries/{delivery}/proof', [DeliveryController::class, 'uploadProof']);
        Route::post('/driver/deliveries/{delivery}/collect-cash', [DeliveryController::class, 'collectCash']);

    });

});
