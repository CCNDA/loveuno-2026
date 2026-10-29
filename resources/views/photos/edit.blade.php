@extends('layouts.app')

@section('title', '我的大頭照')

@section('content')
<div class="member-head">
    @include('partials.avatar', ['user' => auth()->user(), 'size' => 'lg'])
    <h1>我的大頭照</h1>
</div>

@if ($profile->photo_status === 'pending')
    <p class="notice" role="status">您的照片正在審核中，通過以前只有您自己看得到。</p>
@elseif ($profile->photo_status === 'rejected')
    <p class="notice" role="status">上一張照片沒有通過審核，請換一張清楚的本人照片。</p>
@endif

<form method="POST" action="{{ route('photo.update') }}" enctype="multipart/form-data" class="form" novalidate>
    @csrf
    @if ($errors->any())
        <div class="error-summary" role="alert" aria-labelledby="error-title">
            <h2 id="error-title">照片沒有上傳成功</h2>
            <ul>
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#{{ $field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif
    <div class="field">
        <label for="photo">選一張照片</label>
        <p id="photo-hint" class="hint">JPG、PNG 或 WebP，5MB 以下。會裁成正方形；照片裡的拍攝地點等資訊會被拿掉。</p>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
               aria-describedby="photo-hint @error('photo') photo-error @enderror"
               @error('photo') aria-invalid="true" @enderror>
        @error('photo')<p id="photo-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="button">上傳</button>
</form>

@if ($profile->photo_path)
    <form method="POST" action="{{ route('photo.destroy') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="link-button">刪掉目前的照片</button>
    </form>
@endif

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
@endsection
