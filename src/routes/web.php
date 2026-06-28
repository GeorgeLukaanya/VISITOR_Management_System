<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UssdController;
use App\Http\Middleware\VerifyUssdCallback;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard auth (no public registration — accounts are provisioned by us).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Tenant / building-manager dashboard + scoped CSV export.
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');
});

// Africa's Talking USSD callback. POSTed on every step of the session.
// Configure this URL (e.g. https://<host>/ussd) in the AT dashboard / sandbox.
// VerifyUssdCallback authenticates the caller with AT_CALLBACK_SECRET when set
// (no-op while empty, so the local simulator keeps working).
Route::post('/ussd', UssdController::class)
    ->middleware(VerifyUssdCallback::class)
    ->name('ussd.callback');
