<?php

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->middleware('throttle:10,1')->name('invite.accept');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:20,1')->name('two-factor.verify');
});

Route::middleware(['auth', 'secure'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/resend', [UserController::class, 'resendInvite'])->name('users.resend');
    Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset2fa');
    Route::post('/organisations', [OrganisationController::class, 'store'])->name('organisations.store');
});
