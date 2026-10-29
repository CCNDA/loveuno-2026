@extends('layouts.app')

@section('title', '個人相簿')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'album'])
<h2>個人相簿</h2>
<p class="hint">相簿照片上限為 {{ $max }} 張（目前 {{ $photos->count() }} 張）。檔案格式：JPG、PNG，單一檔案不超過 5MB。管理員審核通過後，其他會員才看得到。</p>

@if ($photos->count() < $max)
    <form method="POST" action="{{ route('album.store') }}" class="form" enctype="multipart/form-data" novalidate>
        @csrf
        @include('partials.errors', ['title' => '照片還沒放進相簿'])
        <div class="field">
            <label for="photo">選一張照片</label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" required
                   @error('photo') aria-invalid="true" aria-describedby="photo-error" @enderror>
            @error('photo')<p id="photo-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">放進相簿</button>
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
                <img src="{{ route('album.show', $ph) }}" alt="我的相簿第 {{ $i + 1 }} 張" loading="lazy">
                <p class="meta">{{ $ph->status === 'approved' ? '已經通過審核' : '審核中' }}</p>
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
@endsection
