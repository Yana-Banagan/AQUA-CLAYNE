<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\DriverController;

Route::get('/', function () {
    return view('admin.login');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])
        ->name('admin.login');

    Route::post('/admin/login', [LoginController::class, 'login'])
        ->name('admin.login.submit');
});

Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::view('/dashboard', 'admin.dashboard')
            ->name('dashboard');

        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('logout');

        Route::resource('drivers', DriverController::class)
            ->except(['show', 'destroy']);

        Route::post(
            '/drivers/{driver}/toggle-status',
            [DriverController::class, 'toggleStatus']
        )->name('drivers.toggle-status');
    });
