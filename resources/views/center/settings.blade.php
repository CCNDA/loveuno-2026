{{-- 帳號管理頁裡的「帳號設定」。排法照設計稿 帳號管理 253:15337（電腦）／274:7986（手機）（小鹿 11/6 視覺比對 #3–#5）：
     第一列 會員編號、註冊日期、通知（E-mail、推播）、帳號（帳號開關）；下面 E-mail｜密碼、時區｜文字、語言｜字型；最後「其它」。
     E-mail、密碼、手機的「修改」打開下面的小表單（要輸入目前的密碼，所以不能放進即時存檔的表單）。$user --}}
@php($bag = $errors->getBag('settings'))
@php($ebag = $errors->getBag('email'))
{{-- 換手機和換密碼都用預設的錯誤袋、都有 current_password：用送出時有沒有 phone 欄位分辨是哪一張 --}}
@php($phoneOpen = $errors->has('phone') || $errors->has('phone_country') || ($errors->has('current_password') && old('phone') !== null))
@php($pwOpen = ! $phoneOpen && ($errors->has('password') || $errors->has('password_confirmation') || $errors->has('current_password')))
@php($tz = app()->bound('display_tz') ? app('display_tz') : config('app.timezone'))
<h2 id="settings-title">帳號管理</h2>

<form method="POST" action="{{ route('settings.update') }}" class="form account-grid" aria-labelledby="settings-title" novalidate
      data-autosave="{{ route('settings.patch') }}" data-autosave-whole>
    @csrf
    @method('PUT')

    <div class="acct-item"><span class="acct-label">會員編號</span><span class="acct-value">{{ $user->id }}</span></div>
    <div class="acct-item"><span class="acct-label">註冊日期</span><span class="acct-value">{{ $user->created_at?->shown()->format('Y/m/d') }}</span></div>

    <fieldset class="acct-item acct-switches">
        <legend class="acct-label">通知</legend>
        <div class="acct-switch-row">
            <div class="field-check">
                <label for="notify_email">E-mail</label>
                <input id="notify_email" name="notify_email" type="checkbox" role="switch" class="switch" value="1" aria-describedby="notify_email-hint" @checked($bag->any() ? old('notify_email') : $user->notify_email)>
            </div>
            {{-- 推播是一台裝置一台裝置開的（push.js 管）；沒有 name，不會跟著即時存檔送出 --}}
            <div class="field-check" id="push-box" data-key="{{ config('loveuno.push.public_key') }}">
                <label for="push-switch">推播</label>
                <input id="push-switch" type="checkbox" role="switch" class="switch" aria-describedby="push-status" disabled>
            </div>
        </div>
        <p id="notify_email-hint" class="hint">E-mail：有人傳紙條、送禮物給您時寄信告訴您。</p>
        <p id="push-status" class="hint" role="status">推播要先開啟 JavaScript 才能設定。</p>
    </fieldset>

    <fieldset class="acct-item acct-switches">
        <legend class="acct-label">帳號</legend>
        <div class="field-check">
            <label for="is_active">帳號開關</label>
            <input id="is_active" name="is_active" type="checkbox" role="switch" class="switch" value="1" aria-describedby="is_active-hint" @checked($bag->any() ? old('is_active') : $user->is_active)>
        </div>
        <p id="is_active-hint" class="hint">關掉就是暫停：其他會員看不到您的檔案和日記。資料都還在，隨時可以再打開。</p>
    </fieldset>

    <div class="field acct-wide">
        <span class="acct-label" id="email-label">E-mail</span>
        <div class="acct-row">
            <span class="acct-box">{{ $user->email }}</span>
            <button type="button" class="acct-edit" data-panel="email-edit" aria-controls="email-edit" aria-expanded="false" hidden>修改<span class="visually-hidden"> E-mail</span></button>
        </div>
    </div>
    <div class="field acct-wide">
        <span class="acct-label" id="pw-label">密碼</span>
        <div class="acct-row">
            <span class="acct-box">已設定（為了安全不顯示）</span>
            <button type="button" class="acct-edit" data-panel="pw-edit" aria-controls="pw-edit" aria-expanded="false" hidden>修改<span class="visually-hidden">密碼</span></button>
        </div>
    </div>

    {{-- 時區：熊叔叔 10/29「直接偵測」——照瀏覽器自動換（DisplayTimezone），所以這裡只顯示、不讓選 --}}
    <div class="field acct-wide">
        <span class="acct-label" id="tz-label">時區</span>
        <span class="acct-box">(GMT{{ now()->setTimezone($tz)->format('P') }}) {{ str_replace('_', ' ', \Illuminate\Support\Str::afterLast($tz, '/')) }}</span>
        <p id="tz-hint" class="hint">依您的裝置自動偵測，時間都會照這個時區顯示。</p>
    </div>
    <div class="acct-wide">
        @include('partials.select-field', ['name' => 'font_size', 'label' => '文字大小', 'options' => \App\Http\Controllers\SettingsController::FONT_SIZES, 'value' => $bag->any() ? old('font_size') : $user->font_size, 'required' => true])
    </div>
    <div class="acct-wide">
        @include('partials.select-field', ['name' => 'script', 'label' => '語言', 'options' => \App\Http\Controllers\SettingsController::SCRIPTS, 'value' => $bag->any() ? old('script') : (session('script') ?? $user->script), 'required' => true])
    </div>
    <div class="acct-wide">
        @include('partials.select-field', ['name' => 'font_family', 'label' => '字型', 'options' => \App\Http\Controllers\SettingsController::FONT_FAMILIES, 'value' => $bag->any() ? old('font_family') : $user->font_family, 'hint' => '粉圓是比較圓潤可愛的字體；手機網路慢的話，第一次會比較久。'])
    </div>

    {{-- 手機號碼：設計稿沒有，但換號碼要在這裡（簡訊認證） --}}
    @php($phoneUntil = \App\Support\Phone::expiresAt($user->phone_verified_at))
    <div class="field acct-wide">
        <span class="acct-label" id="phone-label">手機號碼</span>
        <div class="acct-row">
            <span class="acct-box">@if ($user->phone)已認證：{{ \App\Support\Sms::mask($user->phone) }}@else已認證@endif</span>
            <button type="button" class="acct-edit" data-panel="phone-edit" aria-controls="phone-edit" aria-expanded="false" hidden>修改<span class="visually-hidden">手機號碼</span></button>
        </div>
        @if ($phoneUntil)<p class="hint">認證到 {{ $phoneUntil->shown()->format('Y/n/j') }}，之後要重新認證一次</p>@endif
    </div>

    <button type="submit" class="button pill-submit">存設定</button>
</form>

{{-- 「修改」打開的小表單：沒有 JS 時全部直接顯示 --}}
<section id="email-edit" class="acct-panel" data-open="{{ $ebag->any() ? '1' : '0' }}" aria-labelledby="email-change-title">
<form method="POST" action="{{ route('email.change') }}" class="form" novalidate>
    @csrf
    <h3 id="email-change-title">修改 E-mail</h3>
    <p id="new_email-hint" class="hint">目前：{{ $user->email }}。輸入新的電子郵件和密碼，我們會寄確認信到新信箱，點了才會換。</p>
    <div class="field">
        <label for="new_email">新的電子郵件</label>
        <input id="new_email" name="new_email" type="email" inputmode="email" autocomplete="email" maxlength="100" value="{{ $ebag->any() ? old('new_email') : '' }}"
               aria-describedby="new_email-hint{{ $ebag->has('new_email') ? ' new_email-error' : '' }}" @if ($ebag->has('new_email')) aria-invalid="true" @endif>
        @if ($ebag->has('new_email'))<p id="new_email-error" class="field-error" role="alert">{{ $ebag->first('new_email') }}</p>@endif
    </div>
    <div class="field">
        <label for="email_current_password">目前的密碼</label>
        <input id="email_current_password" name="current_password" type="password" autocomplete="current-password"
               @if ($ebag->has('current_password')) aria-invalid="true" aria-describedby="email_current_password-error" @endif>
        @if ($ebag->has('current_password'))<p id="email_current_password-error" class="field-error" role="alert">{{ $ebag->first('current_password') }}</p>@endif
    </div>
    <button type="submit" class="button">寄確認信到新信箱</button>
</form>
</section>

<section id="pw-edit" class="acct-panel" data-open="{{ $pwOpen ? '1' : '0' }}" aria-labelledby="pw-title">
<form method="POST" action="{{ route('center.password') }}" class="form" novalidate>
    @csrf
    @method('PUT')
    <h3 id="pw-title">換密碼</h3>
    @if ($pwOpen) @include('partials.errors', ['title' => '密碼沒有換成功']) @endif
    <div class="field">
        <label for="current_password">目前的密碼</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
               @if ($pwOpen && $errors->has('current_password')) aria-invalid="true" aria-describedby="current_password-error" @endif>
        @if ($pwOpen && $errors->has('current_password'))<p id="current_password-error" class="field-error">{{ $errors->first('current_password') }}</p>@endif
    </div>
    <div class="field">
        <label for="password">新密碼</label>
        <p id="password-hint" class="hint">至少 10 個字元。換好之後，其他裝置上的登入會被登出。</p>
        <input id="password" name="password" type="password" autocomplete="new-password" required
               aria-describedby="password-hint @error('password') password-error @enderror"
               @error('password') aria-invalid="true" @enderror>
        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="password_confirmation">再輸入一次新密碼</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button type="submit" class="button">換密碼</button>
</form>
</section>

@php($country = \App\Support\Phone::countryFromPlace($user->profile?->live_place))
<section id="phone-edit" class="acct-panel" data-open="{{ $phoneOpen ? '1' : '0' }}" aria-labelledby="phone-title">
<form method="POST" action="{{ route('phone.send') }}" class="form" novalidate>
    @csrf
    <h3 id="phone-title">換手機號碼</h3>
    @include('partials.select-field', ['name' => 'phone_country', 'label' => '國碼', 'options' => \App\Support\Phone::COUNTRIES, 'value' => old('phone_country', $country)])
    <div class="field">
        <label for="phone">新的手機號碼</label>
        <p id="phone-hint" class="hint">換號碼要先輸入密碼；新號碼收到認證碼、輸入正確後才會換掉。國外的號碼先選國碼，再填門號。</p>
        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel-national" maxlength="16" value="{{ old('phone', '') }}"
               aria-describedby="phone-hint @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror>
        @error('phone')<p id="phone-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="phone_current_password">目前的密碼</label>
        <input id="phone_current_password" name="current_password" type="password" autocomplete="current-password"
               @if ($phoneOpen && $errors->has('current_password')) aria-invalid="true" @endif>
    </div>
    <button type="submit" class="button">寄認證碼到新號碼</button>
</form>
</section>

<h3 class="settings-other">其它</h3>
<ul class="settings-actions">
    <li><a class="button button-light btn-icon" href="{{ route('settings.network') }}"><span class="ui-icon icon-wifi" aria-hidden="true"></span>網路檢測</a></li>
    <li><a class="button button-light btn-icon" href="{{ route('contact', ['subject' => 'bug']) }}"><span class="ui-icon icon-cs" aria-hidden="true"></span>Bug 回報</a></li>
    <li><a class="button button-light btn-icon" href="{{ route('settings.delete') }}"><span class="ui-icon icon-clear" aria-hidden="true"></span>註銷帳號</a></li>
</ul>
<script src="{{ asset('js/push.js') }}?v={{ $appVersion }}" defer></script>
<script src="{{ asset('js/account-panels.js') }}?v={{ $appVersion }}" defer></script>
