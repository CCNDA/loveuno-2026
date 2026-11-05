@extends('layouts.app')

@section('title', '個人相簿')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'album'])
<h2>個人相簿</h2>
<p id="album-hint" class="hint">相簿照片上限為 {{ $max }} 張（還可以放 {{ $left }} 張），可以一次選好幾張。檔案格式：JPG、PNG，單一檔案不超過 5MB。放上去，其他會員就看得到。</p>

@if ($left > 0)
    <form method="POST" action="{{ route('album.store') }}" class="form" enctype="multipart/form-data" novalidate>
        @csrf
        @include('partials.errors', ['title' => '照片還沒放進相簿'])
        <div class="field">
            <label for="photos">選照片（可以選好幾張）</label>
            @php($err = $errors->first('photos') ?: $errors->first('photos.*'))
            {{-- 整個框就是按鈕（熊叔叔 11/5「多一個選擇檔案很難看」） --}}
            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png" multiple required class="camera-input photo-pick"
                   aria-describedby="album-hint{{ $err ? ' photos-error' : '' }}" @if ($err) aria-invalid="true" @endif>
            <label for="photos" class="camera-box photo-box"><img class="photo-box-icon" src="{{ asset('images/brand/camera.svg') }}" alt="" width="32" height="32"><span class="camera-text">點這裡選照片（可以選好幾張）</span></label>
            @if ($err)<p id="photos-error" class="field-error">{{ $err }}</p>@endif
        </div>
        <button type="submit" class="button">匯入資料</button>
    </form>
@else
    <p class="notice" role="status">相簿滿了（{{ $max }} 張），要放新的請先刪掉一張。</p>
@endif

@if ($photos->isEmpty())
    <p>相簿還沒有照片。</p>
@else
    <ul class="album-grid">
        @foreach ($photos as $i => $ph)
            <li class="album-item">
                @if ($ph->status === 'rejected')
                    <p class="album-rejected">這張沒有通過管理員審核，已經刪掉了。</p>
                @else
                    <img src="{{ route('album.show', $ph) }}" alt="我的相簿第 {{ $i + 1 }} 張" loading="lazy">
                @endif
                <form method="POST" action="{{ route('album.destroy', $ph) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-button">刪掉<span class="visually-hidden">第 {{ $i + 1 }} 張</span></button>
                </form>
            </li>
        @endforeach
    </ul>
@endif

<p><a href="{{ route('members.show', ['user' => auth()->user(), 'tab' => 'album']) }}">看看別人看到的相簿 →</a></p>

{{-- 設計稿「其他／個人化封面」：10 張插圖選一張，顯示在個人首頁最上面 --}}
<h2 id="other-title">其他</h2>
<form method="POST" action="{{ route('cover.preset') }}" class="cover-presets" data-autosave="{{ route('cover.preset.patch') }}">
    @csrf
    @php($chosen = auth()->user()->profile?->coverPreset() ?? 1)
    <fieldset>
        <legend id="cover-title">個人化封面</legend>
        <ul class="cover-preset-grid">
            @foreach (\App\Models\Profile::COVER_PRESETS as $n => $ref)
                <li>
                    <input type="radio" name="cover_preset" id="cover-{{ $n }}" value="{{ $n }}" @checked($chosen === $n)>
                    <button type="button" class="zoom-btn" data-zoom-src="{{ asset('images/covers/cover-' . $n . '.svg') }}" aria-label="放大看第 {{ $n }} 張封面"><span aria-hidden="true">🔍</span></button>
                    <label for="cover-{{ $n }}"><img src="{{ asset('images/covers/cover-' . $n . '.svg') }}" alt="第 {{ $n }} 張（{{ $ref }}）" width="551" height="165" loading="lazy"></label>
                </li>
            @endforeach
        </ul>
    </fieldset>
    <button type="submit" class="button pill-submit">儲存封面</button>
</form>
@endsection
