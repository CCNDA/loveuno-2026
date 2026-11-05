@extends('layouts.app')

@section('title', $diary->exists ? '修改日記' : ($diary->is_prayer ? '寫代禱事項' : '寫心情日記'))

@section('content')
<h1>{{ $diary->exists ? '修改日記' : ($diary->is_prayer ? '寫代禱事項' : '寫心情日記') }}</h1>

<form method="POST" action="{{ $diary->exists ? route('diaries.update', $diary) : route('diaries.store') }}" class="form form-wide diary-form" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($diary->exists) @method('PUT') @endif
    @include('partials.errors', ['title' => '日記還沒存好'])

    @include('diaries.partials.form-fields')
    @if ($diary->exists && $diary->image_path)
        <div class="field">
            <img class="diary-image diary-image-thumb" src="{{ route('diaries.image', $diary) }}?v={{ $diary->image_updated_at?->timestamp }}" alt="目前附的照片">
            <p class="hint">選一張新的就會換掉這張。</p>
            <div class="field-check">
                <input id="remove_image" name="remove_image" type="checkbox" value="1">
                <label for="remove_image">拿掉這張照片</label>
            </div>
        </div>
    @endif

    @include('diaries.partials.visibility-field')

    <button type="submit" class="button pill-submit">{{ $diary->exists ? '存檔' : '發佈' }}</button>
</form>
<script src="{{ asset('js/write-dialog.js') }}?v={{ $appVersion }}" defer></script>

@if ($diary->exists)
    <p class="danger-zone"><a href="{{ route('diaries.delete', $diary) }}">刪掉這篇日記…</a></p>
@endif
@endsection
