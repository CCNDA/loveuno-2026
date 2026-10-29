<?php

use App\Http\Controllers\Admin\PhotoReviewController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\Admin\ReportReviewController;
use App\Http\Controllers\Admin\VerificationReviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PhoneVerifyController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryInteractionController;
use App\Http\Controllers\DiaryWriteController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\LoveHistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// 沒登入：介紹頁；登入後：日記牆（好友推薦＋日記卡片）
Route::get('/', HomeController::class)->name('home');
Route::view('/rules', 'rules')->name('rules');
// 公開頁（設計稿 PageAbout、PageFaq、PageWebsitePolicy、隱私權政策、PageContactUs）
Route::view('/about', 'pages.about')->name('about');
Route::view('/faq', 'pages.faq')->name('faq');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
Route::post('/contact', [\App\Http\Controllers\PageController::class, 'storeContact'])->middleware('throttle:5,60,contact')->name('contact.store');
// 職業 Email 認證信的連結（不用登入；確認碼對了才算）
Route::get('/verify/job/{verification}/{token}', [VerificationController::class, 'showConfirmJob'])
    ->whereNumber('verification')->middleware('throttle:20,60,verify-job-confirm')->name('verify.job.confirm');
Route::post('/verify/job/{verification}/{token}', [VerificationController::class, 'confirmJob'])
    ->whereNumber('verification')->middleware('throttle:20,60,verify-job-confirm')->name('verify.job.confirm.store');

// 繁／简切換：存在 session；回上一頁只回本站（不跟著外站的 Referer 走）
Route::get('/script/{script}', function (\Illuminate\Http\Request $request, string $script) {
    $request->session()->put('script', $script);
    $back = (string) $request->headers->get('referer');
    $sameSite = $back !== '' && parse_url($back, PHP_URL_HOST) === $request->getHost();

    return redirect($sameSite ? $back : '/');
})->whereIn('script', ['hans', 'hant'])->name('script');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // 忘記密碼：不放在 registration.open 裡面，註冊關著也要能重設
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,10,password-forgot')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,10,password-reset')->name('password.update');
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
// 手機認證：電郵驗證好了、手機還沒驗證的人來這裡
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/phone/verify', [PhoneVerifyController::class, 'show'])->name('phone.notice');
    Route::post('/phone/send', [PhoneVerifyController::class, 'send'])->middleware('throttle:10,60,phone-send')->name('phone.send');
    Route::post('/phone/verify', [PhoneVerifyController::class, 'verify'])->middleware('throttle:20,60,phone-verify')->name('phone.verify');
    Route::post('/phone/reset', [PhoneVerifyController::class, 'reset'])->name('phone.reset');
});

Route::middleware(['auth', 'auth.session', 'verified', 'phone.verified'])->group(function () {
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
    // 帳號設定（設計稿 PageMemberSettings）
    Route::put('/center/settings', [SettingsController::class, 'update'])->middleware('throttle:30,60,settings-save')->name('settings.update');
    Route::get('/center/network', [SettingsController::class, 'network'])->name('settings.network');
    // Web Push（public/js/push.js、public/sw.js）
    Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:20,60,push-subscribe')->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::get('/push/summary', [PushController::class, 'summary'])->name('push.summary');
    Route::post('/center/email', [EmailChangeController::class, 'request'])->middleware('throttle:10,60,email-change')->name('email.change');
    Route::get('/email/change/{user}/{hash}', [EmailChangeController::class, 'confirm'])->whereNumber('user')->middleware('signed')->name('email.change.confirm');
    Route::get('/center/delete', [SettingsController::class, 'confirmDestroy'])->name('settings.delete');
    Route::delete('/center/account', [SettingsController::class, 'destroy'])->middleware('throttle:5,60,account-delete')->name('settings.destroy');
    Route::get('/center/preferences', [CenterController::class, 'preferences'])->name('center.preferences');
    Route::put('/center/preferences', [CenterController::class, 'updatePreferences'])->middleware('throttle:30,60,preferences-save')->name('center.preferences.update');
    Route::get('/me/intro', [ProfileController::class, 'editIntro'])->name('profile.intro');
    Route::get('/me/love', [LoveHistoryController::class, 'edit'])->name('profile.love');
    // 身份認證（職業 Email、身分證明文件、教會週報；手機在 PhoneVerifyController）
    Route::get('/me/verify', [VerificationController::class, 'index'])->name('verify.index');
    Route::post('/me/verify/job', [VerificationController::class, 'storeJob'])->middleware('throttle:5,60,verify-job')->name('verify.job');
    Route::post('/me/verify/id', [VerificationController::class, 'storeId'])->middleware('throttle:10,60,verify-upload')->name('verify.id');
    Route::post('/me/verify/church', [VerificationController::class, 'storeChurch'])->middleware('throttle:10,60,verify-upload')->name('verify.church');
    Route::put('/me/love', [LoveHistoryController::class, 'update'])->middleware('throttle:30,60,profile-save')->name('profile.love.update');
    // 個人相簿（最多 9 張，要審核）
    Route::get('/me/album', [AlbumController::class, 'edit'])->name('album.edit');
    Route::post('/me/album', [AlbumController::class, 'store'])->middleware('throttle:20,60,album-upload')->name('album.store');
    Route::delete('/me/album/{photo}', [AlbumController::class, 'destroy'])->whereNumber('photo')->name('album.destroy');
    Route::get('/album/{photo}', [AlbumController::class, 'show'])->whereNumber('photo')->name('album.show');
    Route::put('/me/intro', [ProfileController::class, 'updateIntro'])->middleware('throttle:30,60,profile-save')->name('profile.intro.update');
    Route::put('/me', [ProfileController::class, 'update'])->middleware('throttle:30,60,profile-save')->name('profile.update');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');
    // 封鎖（先到確認頁）
    Route::get('/members/{user}/block', [BlockController::class, 'confirm'])->whereNumber('user')->name('blocks.confirm');
    Route::post('/members/{user}/block', [BlockController::class, 'store'])->whereNumber('user')->middleware('throttle:30,60,block')->name('blocks.store');
    Route::delete('/members/{user}/block', [BlockController::class, 'destroy'])->whereNumber('user')->name('blocks.destroy');
    // 送禮物（一次 3 點、同一位會員一天一份；互送＝普通好友）
    Route::get('/gifts', [GiftController::class, 'index'])->name('gifts.index');
    Route::get('/members/{user}/gift', [GiftController::class, 'create'])->whereNumber('user')->name('gifts.create');
    Route::post('/members/{user}/gift', [GiftController::class, 'store'])->whereNumber('user')->middleware('throttle:30,60,gift-send')->name('gifts.store');
    // 好友：我的朋友、解除關係
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::get('/members/{user}/unfriend', [FriendController::class, 'confirmDestroy'])->whereNumber('user')->name('friends.confirm');
    Route::delete('/members/{user}/friend', [FriendController::class, 'destroy'])->whereNumber('user')->name('friends.destroy');
    // 檢舉（封鎖了也可以檢舉）
    Route::get('/members/{user}/report', [ReportController::class, 'create'])->whereNumber('user')->name('reports.create');
    Route::post('/members/{user}/report', [ReportController::class, 'store'])->whereNumber('user')->middleware('throttle:10,1440,report')->name('reports.store');
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    // 寫日記（create 要排在 {diary} 前面）；改、刪只有作者本人
    Route::get('/diaries/create', [DiaryWriteController::class, 'create'])->name('diaries.create');
    Route::post('/diaries', [DiaryWriteController::class, 'store'])->middleware('throttle:20,60,diary-create')->name('diaries.store');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->whereNumber('diary')->name('diaries.show');
    Route::get('/diaries/{diary}/edit', [DiaryWriteController::class, 'edit'])->whereNumber('diary')->name('diaries.edit');
    Route::put('/diaries/{diary}', [DiaryWriteController::class, 'update'])->whereNumber('diary')->middleware('throttle:30,60,diary-update')->name('diaries.update');
    Route::get('/diaries/{diary}/delete', [DiaryWriteController::class, 'confirmDestroy'])->whereNumber('diary')->name('diaries.delete');
    Route::delete('/diaries/{diary}', [DiaryWriteController::class, 'destroy'])->whereNumber('diary')->name('diaries.destroy');

    // 為你禱告（再按一次取消）、留言、附圖
    Route::post('/diaries/{diary}/pray', [DiaryInteractionController::class, 'pray'])->whereNumber('diary')->middleware('throttle:60,10,diary-pray')->name('diaries.pray');
    Route::post('/diaries/{diary}/comments', [DiaryInteractionController::class, 'comment'])->whereNumber('diary')->middleware('throttle:20,10,diary-comment')->name('diaries.comments.store');
    Route::delete('/diaries/{diary}/comments/{comment}', [DiaryInteractionController::class, 'destroyComment'])->whereNumber(['diary', 'comment'])->name('diaries.comments.destroy');
    Route::get('/diaries/{diary}/image', [DiaryInteractionController::class, 'image'])->whereNumber('diary')->name('diaries.image');

    // 大頭照（上傳要審核；檔案經過 PhotoController 檢查權限才送出）
    Route::get('/me/photo', [PhotoController::class, 'edit'])->defaults('kind', 'photo')->name('photo.edit');
    Route::post('/me/photo', [PhotoController::class, 'update'])->defaults('kind', 'photo')->middleware('throttle:10,60,photo-upload')->name('photo.update');
    Route::delete('/me/photo', [PhotoController::class, 'destroy'])->defaults('kind', 'photo')->name('photo.destroy');
    Route::get('/photos/{user}', [PhotoController::class, 'show'])->defaults('kind', 'photo')->name('photos.show');
    // 個人化封面（跟大頭照同一支程式，kind=cover）
    Route::get('/me/cover', [PhotoController::class, 'edit'])->defaults('kind', 'cover')->name('cover.edit');
    Route::post('/me/cover', [PhotoController::class, 'update'])->defaults('kind', 'cover')->middleware('throttle:10,60,cover-upload')->name('cover.update');
    Route::delete('/me/cover', [PhotoController::class, 'destroy'])->defaults('kind', 'cover')->name('cover.destroy');
    Route::get('/covers/{user}', [PhotoController::class, 'show'])->defaults('kind', 'cover')->name('covers.show');

    // 管理員
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/photos', [PhotoReviewController::class, 'index'])->name('admin.photos');
        Route::post('/photos/{profile}', [PhotoReviewController::class, 'decide'])->name('admin.photos.decide');
        Route::get('/verifications', [VerificationReviewController::class, 'index'])->name('admin.verifications');
        Route::post('/verifications/{verification}', [VerificationReviewController::class, 'decide'])->whereNumber('verification')->name('admin.verifications.decide');
        Route::get('/verifications/{verification}/photo', [VerificationReviewController::class, 'photo'])->whereNumber('verification')->name('admin.verifications.photo');
        Route::get('/contacts', [\App\Http\Controllers\Admin\ContactReviewController::class, 'index'])->name('admin.contacts');
        Route::post('/contacts/{message}', [\App\Http\Controllers\Admin\ContactReviewController::class, 'handle'])->whereNumber('message')->name('admin.contacts.handle');
        Route::get('/reports', [ReportReviewController::class, 'index'])->name('admin.reports');
        Route::post('/reports/{report}/close', [ReportReviewController::class, 'close'])->whereNumber('report')->name('admin.reports.close');
        Route::get('/reports/{report}/screenshot', [ReportReviewController::class, 'screenshot'])->whereNumber('report')->name('admin.reports.screenshot');
    });
});
