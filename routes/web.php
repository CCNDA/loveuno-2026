<?php

use App\Http\Controllers\Admin\PhotoReviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryWriteController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
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
    ->middleware(['signed', 'throttle:6,1,verify-link'])->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // 已登入但還沒驗證
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:3,10,verify-resend')->name('verification.send');
});

// 個人檔案與日記都要登入、email 已驗證才能看（舊站 2026-10-28 起也是要登入）
// auth.session：換密碼後其他裝置的 session 會失效（logoutOtherDevices 要配這個才有用）
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/me', [MemberController::class, 'me'])->name('me');
    Route::get('/me/edit', [ProfileController::class, 'edit'])->name('profile.edit');

    // 限流的第三個參數是名稱：沒有名稱時，同一個人所有限流共用同一個計數（Laravel 用 user id 當 key），
// 存檔幾次就會把換密碼也擋掉（兩面審查 10/29 發現）。
// 傳紙條（一對一；同一人 10 分鐘最多寄 30 張）
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/{user}', [NoteController::class, 'show'])->whereNumber('user')->name('notes.show');
    Route::post('/notes/{user}', [NoteController::class, 'store'])->whereNumber('user')->middleware('throttle:30,10,note-send')->name('notes.store');

    // 會員中心（帳號管理／個人檔案／愛積點／交友條件）
    Route::redirect('/center', '/center/account');
    Route::get('/center/account', [CenterController::class, 'account'])->name('center.account');
    Route::put('/center/password', [CenterController::class, 'updatePassword'])->middleware('throttle:5,10,password-change')->name('center.password');
    Route::get('/center/points', [CenterController::class, 'points'])->name('center.points');
    Route::get('/center/preferences', [CenterController::class, 'preferences'])->name('center.preferences');
    Route::put('/center/preferences', [CenterController::class, 'updatePreferences'])->middleware('throttle:30,60,preferences-save')->name('center.preferences.update');
    Route::put('/me', [ProfileController::class, 'update'])->middleware('throttle:30,60,profile-save')->name('profile.update');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    // 寫日記（create 要排在 {diary} 前面）；改、刪只有作者本人
    Route::get('/diaries/create', [DiaryWriteController::class, 'create'])->name('diaries.create');
    Route::post('/diaries', [DiaryWriteController::class, 'store'])->middleware('throttle:20,60,diary-create')->name('diaries.store');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->whereNumber('diary')->name('diaries.show');
    Route::get('/diaries/{diary}/edit', [DiaryWriteController::class, 'edit'])->whereNumber('diary')->name('diaries.edit');
    Route::put('/diaries/{diary}', [DiaryWriteController::class, 'update'])->whereNumber('diary')->middleware('throttle:30,60,diary-update')->name('diaries.update');
    Route::get('/diaries/{diary}/delete', [DiaryWriteController::class, 'confirmDestroy'])->whereNumber('diary')->name('diaries.delete');
    Route::delete('/diaries/{diary}', [DiaryWriteController::class, 'destroy'])->whereNumber('diary')->name('diaries.destroy');

    // 大頭照（上傳要審核；檔案經過 PhotoController 檢查權限才送出）
    Route::get('/me/photo', [PhotoController::class, 'edit'])->name('photo.edit');
    Route::post('/me/photo', [PhotoController::class, 'update'])->middleware('throttle:10,60,photo-upload')->name('photo.update');
    Route::delete('/me/photo', [PhotoController::class, 'destroy'])->name('photo.destroy');
    Route::get('/photos/{user}', [PhotoController::class, 'show'])->name('photos.show');

    // 管理員
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/photos', [PhotoReviewController::class, 'index'])->name('admin.photos');
        Route::post('/photos/{profile}', [PhotoReviewController::class, 'decide'])->name('admin.photos.decide');
    });
});
