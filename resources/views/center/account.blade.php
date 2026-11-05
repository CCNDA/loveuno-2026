@extends('layouts.app')

@section('title', '帳號管理')

@section('content')
@include('center.tabs', ['current' => 'account'])

@include('center.settings')

{{-- 手機、密碼：設計稿沒有獨立區塊，收在「修改」裡（出錯時自動打開） --}}
<details class="inline-edit" @if ($errors->any()) open @endif>
<summary><span class="inline-edit-label">手機號碼</span> <span class="inline-edit-btn">修改</span></summary>
<h2 id="phone-title" class="visually-hidden">手機號碼</h2>
@php($phoneUntil = \App\Support\Phone::expiresAt(auth()->user()->phone_verified_at))
<p>@if (auth()->user()->phone)已認證：{{ \App\Support\Sms::mask(auth()->user()->phone) }}@else已認證（新版還沒有存號碼）@endif
    @if ($phoneUntil)・認證到 {{ $phoneUntil->shown()->format('Y/n/j') }}，之後要重新認證一次@endif</p>
@php($country = \App\Support\Phone::countryFromPlace(auth()->user()->profile?->live_place))
<form method="POST" action="{{ route('phone.send') }}" class="form" aria-labelledby="phone-title" novalidate>
    @csrf
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
               @error('current_password') aria-invalid="true" @enderror>
    </div>
    <button type="submit" class="button">寄認證碼到新號碼</button>
</form>

</details>

<details class="inline-edit" @if ($errors->any()) open @endif>
<summary><span class="inline-edit-label">密碼</span> <span class="inline-edit-btn">修改</span></summary>
<h2 id="pw-title" class="visually-hidden">換密碼</h2>
<form method="POST" action="{{ route('center.password') }}" class="form" aria-labelledby="pw-title" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '密碼沒有換成功'])

    <div class="field">
        <label for="current_password">目前的密碼</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
               @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
        @error('current_password')<p id="current_password-error" class="field-error">{{ $message }}</p>@enderror
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

</details>

<h2 id="blocked-title">封鎖名單</h2>
@if ($blocked->isEmpty())
    <p class="hint">您沒有封鎖任何人。</p>
@else
    <ul class="plain-list" aria-labelledby="blocked-title">
        @foreach ($blocked as $b)
            <li class="blocked-item">
                {{ $b->name }}
                <form method="POST" action="{{ route('blocks.destroy', $b) }}" class="inline-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-button">解除封鎖<span class="visually-hidden">：{{ $b->name }}</span></button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
@endsection
