@extends('layouts.app')

@section('title', '我的' . $cfg['label'])

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => $kind])
<div class="member-head">
    @if ($kind === 'photo')
        @include('partials.avatar', ['user' => auth()->user(), 'size' => 'lg'])
    @endif
    <h2>我的{{ $cfg['label'] }}</h2>
</div>
@if ($kind === 'cover')
    @include('partials.cover', ['user' => auth()->user(), 'alt' => '我目前的封面'])
@endif

@if ($profile->{$kind . '_status'} === 'pending')
    <p class="notice" role="status">您的{{ $cfg['label'] }}正在審核中，通過以前只有您自己看得到。</p>
@elseif ($profile->{$kind . '_status'} === 'rejected')
    <p class="notice" role="status">上一張{{ $cfg['label'] }}沒有通過審核，請換一張。</p>
@endif

<form method="POST" action="{{ route($kind . '.update') }}" enctype="multipart/form-data" class="form" novalidate>
    @csrf
    @include('partials.errors', ['title' => $cfg['label'] . '沒有上傳成功'])
    <div class="field">
        <label for="photo">選一張照片</label>
        <p id="photo-hint" class="hint">JPG、PNG 或 WebP，5MB 以下。會裁成 {{ $kind === 'photo' ? '正方形' : '橫長方形（寬：高＝3：1）' }}；照片裡的拍攝地點等資訊會被拿掉。</p>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
               aria-describedby="photo-hint @error('photo') photo-error @enderror"
               @error('photo') aria-invalid="true" @enderror>
        @error('photo')<p id="photo-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="button">上傳</button>
</form>

@if ($profile->{$kind . '_path'})
    <form method="POST" action="{{ route($kind . '.destroy') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="link-button">刪掉目前的{{ $cfg['label'] }}</button>
    </form>
@endif

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
@endsection
