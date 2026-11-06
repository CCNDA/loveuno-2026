{{-- 個人大頭貼照片（設計稿 69:5836 自我介紹最上面；熊叔叔 11/6：大頭照不另開一頁，併進自我介紹） --}}
@php($profile = auth()->user()->profile)
<section id="photo-section" class="photo-section" aria-labelledby="photo-title">
<div class="member-head">
    @include('partials.avatar', ['user' => auth()->user(), 'size' => 'lg'])
    <h2 id="photo-title">個人大頭貼照片</h2>
</div>
@if ($profile->photo_status === 'pending')
    <p class="notice" role="status">您的{{ '大頭照' }}正在審核中，通過以前只有您自己看得到。</p>
@elseif ($profile->photo_status === 'rejected')
    <p class="notice" role="status">上一張{{ '大頭照' }}沒有通過審核，請換一張。</p>
@endif

<form method="POST" action="{{ route('photo.update') }}" enctype="multipart/form-data" class="form" novalidate>
    @csrf
    @include('partials.errors', ['title' => '大頭照' . '沒有上傳成功'])
    <div class="field">
        <label for="photo">選一張照片</label>
        <p id="photo-hint" class="hint">JPG、PNG 或 WebP，5MB 以下。會裁成 正方形；照片裡的拍攝地點等資訊會被拿掉。</p>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="camera-input photo-pick"
               aria-describedby="photo-hint @error('photo') photo-error @enderror"
               @error('photo') aria-invalid="true" @enderror>
        <label for="photo" class="camera-box photo-box"><img class="photo-box-icon" src="{{ asset('images/brand/camera.svg') }}" alt="" width="32" height="32"><span class="camera-text">點這裡選照片</span></label>
        @error('photo')<p id="photo-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="button">上傳</button>
</form>

@if ($profile->photo_path)
    <form method="POST" action="{{ route('photo.destroy') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="link-button">刪掉目前的{{ '大頭照' }}</button>
    </form>
@endif

</section>
