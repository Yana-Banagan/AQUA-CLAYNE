<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoginController;

Route::get('/', function () {
    return view('admin.login');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])
        ->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'login'])
        ->name('admin.login.submit');
});

Route::middleware(['auth', 'role:admin,staff'])->prefix('admin')->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('admin.logout');
});
