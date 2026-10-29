{{-- 帳號管理頁裡的「帳號設定」（設計稿 PageMemberSettings）。$user --}}
@php($bag = $errors->getBag('settings'))
<h2 id="settings-title">帳號設定</h2>
<dl class="profile">
    <div><dt>會員編號</dt><dd>{{ $user->id }}</dd></div>
    <div><dt>註冊日期</dt><dd>{{ $user->created_at?->shown()->format('Y/n/j') }}</dd></div>
</dl>

@php($ebag = $errors->getBag('email'))
<form method="POST" action="{{ route('email.change') }}" class="form" aria-labelledby="email-change-title" novalidate>
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

<form method="POST" action="{{ route('settings.update') }}" class="form" aria-labelledby="settings-title" novalidate>
    @csrf
    @method('PUT')

    <fieldset class="field">
        <legend>通知</legend>
        <div class="field-check">
            <input id="notify_email" name="notify_email" type="checkbox" value="1" aria-describedby="notify_email-hint" @checked($bag->any() ? old('notify_email') : $user->notify_email)>
            <label for="notify_email">Email 通知</label>
        </div>
        <p id="notify_email-hint" class="hint">有人傳紙條、送禮物給您的時候，寄 email 告訴您。</p>
    </fieldset>

    <fieldset class="field" id="push-box" data-key="{{ config('loveuno.push.public_key') }}">
        <legend>推播</legend>
        <p id="push-status" class="hint" role="status">要先開啟 JavaScript 才能設定推播。</p>
        <p class="hint">推播是一台裝置一台裝置開的。iPhone 請先用 Safari 的「分享 → 加入主畫面」，再從主畫面打開愛諾園，才能開推播（iOS 16.4 以上）。</p>
        <button type="button" id="push-on" class="button button-light" hidden>在這台裝置開啟推播</button>
        <button type="button" id="push-off" class="button button-light" hidden>在這台裝置關閉推播</button>
    </fieldset>

    <fieldset class="field">
        <legend>帳號</legend>
        <div class="field-check">
            <input id="is_active" name="is_active" type="checkbox" value="1" aria-describedby="is_active-hint" @checked($bag->any() ? old('is_active') : $user->is_active)>
            <label for="is_active">啟用帳號</label>
        </div>
        <p id="is_active-hint" class="hint">關掉就是暫停：其他會員看不到您的檔案和日記，也不能傳紙條、送禮物給您。資料都還在，隨時可以再打開。</p>
    </fieldset>

    @include('partials.select-field', ['name' => 'font_size', 'label' => '字體大小', 'options' => \App\Http\Controllers\SettingsController::FONT_SIZES, 'value' => $bag->any() ? old('font_size') : $user->font_size, 'required' => true])
    @include('partials.select-field', ['name' => 'font_family', 'label' => '字體', 'options' => \App\Http\Controllers\SettingsController::FONT_FAMILIES, 'value' => $bag->any() ? old('font_family') : $user->font_family, 'hint' => '粉圓是比較圓潤可愛的字體；手機網路慢的話，第一次會比較久。'])
    @include('partials.select-field', ['name' => 'script', 'label' => '顯示語言', 'options' => \App\Http\Controllers\SettingsController::SCRIPTS, 'value' => $bag->any() ? old('script') : (session('script') ?? $user->script), 'required' => true])

    <button type="submit" class="button">存設定</button>
</form>

<ul class="settings-actions">
    <li><a class="button button-light" href="{{ route('settings.network') }}">網路檢測</a></li>
    <li><a class="button button-light" href="mailto:{{ config('loveuno.contact_email') }}?subject={{ rawurlencode('愛諾園 Bug 回報') }}">Bug 回報<span class="visually-hidden">（寄 email 到 {{ config('loveuno.contact_email') }}）</span></a></li>
    <li><a class="button button-secondary" href="{{ route('settings.delete') }}">註銷帳號…</a></li>
</ul>
<script src="{{ asset('js/push.js') }}?v={{ $appVersion }}" defer></script>
