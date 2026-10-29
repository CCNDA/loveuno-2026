@extends('layouts.app')

@section('title', $diary->exists ? '修改日記' : '寫日記')

@section('content')
<h1>{{ $diary->exists ? '修改日記' : '寫日記' }}</h1>

<form method="POST" action="{{ $diary->exists ? route('diaries.update', $diary) : route('diaries.store') }}" class="form form-wide" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($diary->exists) @method('PUT') @endif
    @include('partials.errors', ['title' => '日記還沒存好'])

    <div class="field">
        <label for="title">標題</label>
        <input id="title" name="title" type="text" maxlength="150" value="{{ old('title', $diary->title) }}" required
               @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
        @error('title')<p id="title-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="body">內容</label>
        <textarea id="body" name="body" rows="12" maxlength="10000" required
                  @error('body') aria-invalid="true" aria-describedby="body-error" @enderror>{{ old('body', $diary->body) }}</textarea>
        @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-check">
        <input id="is_prayer" name="is_prayer" type="checkbox" value="1" aria-describedby="is_prayer-hint"
               @checked(old('is_prayer', $diary->is_prayer))>
        <label for="is_prayer">這是代禱（請大家為我禱告）</label>
    </div>
    <p id="is_prayer-hint" class="hint">勾了以後，日記上會有「代禱」標籤。</p>

    <div class="field">
        <label for="image">照片（可以不放）</label>
        <p id="image-hint" class="hint">JPG、PNG 或 WebP，5MB 以內。照片要等管理員審核通過，其他會員才看得到。</p>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
               aria-describedby="image-hint @error('image') image-error @enderror"
               @error('image') aria-invalid="true" @enderror>
        @error('image')<p id="image-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    @if ($diary->exists && ! $diary->image_path && $diary->image_status === 'rejected')
        <p class="notice" role="status">上次放的照片沒有通過管理員審核，已經刪掉了。可以換一張再試。</p>
    @endif
    @if ($diary->exists && $diary->image_path)
        <div class="field">
            <img class="diary-image diary-image-thumb" src="{{ route('diaries.image', $diary) }}?v={{ $diary->image_updated_at?->timestamp }}" alt="目前附的照片">
            <p class="hint">{{ $diary->image_status === 'approved' ? '已經通過審核。' : '審核中。' }}換一張就重新審核。</p>
            <div class="field-check">
                <input id="remove_image" name="remove_image" type="checkbox" value="1">
                <label for="remove_image">拿掉這張照片</label>
            </div>
        </div>
    @endif

    <fieldset id="visibility" tabindex="-1" class="field">
        <legend>誰看得到</legend>
        @foreach (['public' => '所有登入的會員', 'private' => '只有我自己'] as $value => $label)
            <div class="field-check">
                <input id="visibility-{{ $value }}" name="visibility" type="radio" value="{{ $value }}"
                       @checked(old('visibility', $diary->visibility) === $value)
                       @error('visibility') aria-invalid="true" aria-describedby="visibility-error" @enderror>
                <label for="visibility-{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
        @error('visibility')<p id="visibility-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <button type="submit" class="button">{{ $diary->exists ? '存檔' : '發表' }}</button>
</form>

@if ($diary->exists)
    <p class="danger-zone"><a href="{{ route('diaries.delete', $diary) }}">刪掉這篇日記…</a></p>
@endif
@endsection
