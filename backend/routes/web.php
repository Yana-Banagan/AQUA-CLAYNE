<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\DeliveryAssignmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RefillOperationController;

Route::get('/', function () {
    return redirect()->route('admin.login');
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

        // ----------------------------------------------------
        // ADMIN DASHBOARD
        // ----------------------------------------------------
    
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // ----------------------------------------------------
        // ADMIN LOGOUT
        // ----------------------------------------------------
    
        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('logout');

        // ----------------------------------------------------
        // DRIVER MANAGEMENT
        // ----------------------------------------------------
    
        Route::resource('drivers', DriverController::class)
            ->except(['show', 'destroy']);

        Route::post(
            '/drivers/{driver}/toggle-status',
            [DriverController::class, 'toggleStatus']
        )->name('drivers.toggle-status');

        // ----------------------------------------------------
        // DELIVERY ASSIGNMENT
        // ----------------------------------------------------
    
        Route::get(
            '/deliveries/assign',
            [
                DeliveryAssignmentController::class,
                'index',
            ]
        )->name('deliveries.assign');

        Route::post(
            '/deliveries/assign',
            [
                DeliveryAssignmentController::class,
                'store',
            ]
        )->name('deliveries.assign.store');

        Route::post('/orders/{order}/transition', [RefillOperationController::class, 'transition'])->name('orders.transition');
        Route::post('/orders/{order}/intake', [RefillOperationController::class, 'receive'])->name('orders.intake');
        Route::post('/refill-queue/{order}/start', [RefillOperationController::class, 'start'])->name('refill-queue.start');
        Route::post('/refill-queue/{order}/complete', [RefillOperationController::class, 'complete'])->name('refill-queue.complete');
        Route::post('/refill-queue/{order}/priority', [RefillOperationController::class, 'priority'])->name('refill-queue.priority');
    });
