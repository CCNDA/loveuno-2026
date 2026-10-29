{{-- 帳號管理頁裡的「帳號設定」（設計稿 PageMemberSettings）。$user --}}
@php($bag = $errors->getBag('settings'))
<h2 id="settings-title">帳號設定</h2>
<dl class="profile">
    <div><dt>會員編號</dt><dd>{{ $user->id }}</dd></div>
    <div><dt>註冊日期</dt><dd>{{ $user->created_at?->format('Y/n/j') }}</dd></div>
</dl>

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

    <fieldset class="field">
        <legend>帳號</legend>
        <div class="field-check">
            <input id="is_active" name="is_active" type="checkbox" value="1" aria-describedby="is_active-hint" @checked($bag->any() ? old('is_active') : $user->is_active)>
            <label for="is_active">啟用帳號</label>
        </div>
        <p id="is_active-hint" class="hint">關掉就是暫停：其他會員看不到您的檔案和日記，也不能傳紙條、送禮物給您。資料都還在，隨時可以再打開。</p>
    </fieldset>

    @include('partials.select-field', ['name' => 'font_size', 'label' => '字體大小', 'options' => \App\Http\Controllers\SettingsController::FONT_SIZES, 'value' => $bag->any() ? old('font_size') : $user->font_size, 'required' => true])
    @include('partials.select-field', ['name' => 'script', 'label' => '顯示語言', 'options' => \App\Http\Controllers\SettingsController::SCRIPTS, 'value' => $bag->any() ? old('script') : (session('script') ?? $user->script), 'required' => true])

    <button type="submit" class="button">存設定</button>
</form>

<ul class="settings-actions">
    <li><a class="button button-light" href="{{ route('settings.network') }}">網路檢測</a></li>
    <li><a class="button button-light" href="mailto:{{ config('loveuno.contact_email') }}?subject={{ rawurlencode('愛諾園 Bug 回報') }}">Bug 回報<span class="visually-hidden">（寄 email 到 {{ config('loveuno.contact_email') }}）</span></a></li>
    <li><a class="button button-secondary" href="{{ route('settings.delete') }}">註銷帳號…</a></li>
</ul>
