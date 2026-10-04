<?php

use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Public pages — prototype UI at root; Blade pages kept as legacy
Route::get('/', fn () => response()->file(public_path('PharmaLocateFrontEnd.html')))->name('home');
Route::get('/classic', [PageController::class, 'home'])->name('classic.home');
Route::get('/pharmacies', [PageController::class, 'pharmacies'])->name('pharmacies');
Route::get('/medicines', [PageController::class, 'medicines'])->name('medicines');

// Guest-only auth pages
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login']);
    Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthWebController::class, 'register']);
    Route::post('/register/confirm', [AuthWebController::class, 'confirmRegister'])->name('register.confirm');
});

Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

// Inquiries require a logged-in account (guests cannot inquire — SS6).
Route::middleware('auth')->group(function () {
    Route::get('/inquiries', [PageController::class, 'inquiries'])->name('inquiries');
    Route::post('/inquiries', [PageController::class, 'storeInquiry'])->name('inquiries.store');
});

// Kept for reference: the JSON API demo page moved to /api-demo.
Route::get('/api-demo', fn () => view('demo'))->name('api-demo');
