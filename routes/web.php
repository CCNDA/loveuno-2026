<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/rules', 'rules')->name('rules');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// 註冊：config loveuno.registration_open 沒打開就 404，登入與否都一樣（11/8 展示版不開放）
Route::middleware('registration.open')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    });
    Route::get('/register/sent', [RegisterController::class, 'sent'])->name('register.sent');
});

// 驗證信裡的連結：不用登入，靠簽章（60 分鐘）＋email hash
Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // 已登入但還沒驗證
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:3,10')->name('verification.send');
});

// 個人檔案與日記都要登入、email 已驗證才能看（舊站 2026-10-28 起也是要登入）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/me', [MemberController::class, 'me'])->name('me');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->name('diaries.show');
});
