<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// 個人檔案與日記都要登入才能看（舊站 2026-10-28 起也是這樣）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/me', [MemberController::class, 'me'])->name('me');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->name('diaries.show');
});
