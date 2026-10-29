@extends('layouts.app')

@section('title', '個人相簿')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'album'])
<h2>個人相簿</h2>
<p id="album-hint" class="hint">相簿照片上限為 {{ $max }} 張（還可以放 {{ $left }} 張），可以一次選好幾張。檔案格式：JPG、PNG，單一檔案不超過 5MB。管理員審核通過後，其他會員才看得到。</p>

@if ($left > 0)
    <form method="POST" action="{{ route('album.store') }}" class="form" enctype="multipart/form-data" novalidate>
        @csrf
        @include('partials.errors', ['title' => '照片還沒放進相簿'])
        <div class="field">
            <label for="photos">選照片（可以選好幾張）</label>
            @php($err = $errors->first('photos') ?: $errors->first('photos.*'))
            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png" multiple required
                   aria-describedby="album-hint{{ $err ? ' photos-error' : '' }}" @if ($err) aria-invalid="true" @endif>
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
                    <p class="meta">{{ $ph->status === 'approved' ? '已經通過審核' : '審核中' }}</p>
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
@endsection
