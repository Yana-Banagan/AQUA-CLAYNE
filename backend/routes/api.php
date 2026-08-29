<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::get('/auth/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum');

});
