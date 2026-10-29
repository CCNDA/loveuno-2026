<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // 註冊：config loveuno.registration_open 沒打開就 404（11/8 展示版不開放）
    Route::middleware('registration.open')->group(function () {
        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    });
});

// email 驗證：要登入，但還沒驗證也能進
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:3,10')->name('verification.send');
});

// 個人檔案與日記都要登入才能看（舊站 2026-10-28 起也是這樣）
Route::middleware('auth')->post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/me', [MemberController::class, 'me'])->name('me');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->name('diaries.show');
});
