@extends('layouts.app')

@section('title', $item->exists ? '修改公告' : '新增公告')

@section('content')
<h1>{{ $item->exists ? '修改公告' : '新增公告' }}</h1>
<p class="meta"><a href="{{ route('admin.news') }}">回公告列表</a></p>
<form method="POST" action="{{ $item->exists ? route('admin.news.update', $item) : route('admin.news.store') }}" class="form form-wide" novalidate>
    @csrf
    @if ($item->exists) @method('PUT') @endif
    @include('partials.errors', ['title' => '公告還沒有存好'])
    <div class="field">
        <label for="title">標題</label>
        <input id="title" name="title" type="text" maxlength="100" value="{{ old('title', $item->title) }}" required
               @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
        @error('title')<p id="title-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="body">內容</label>
        <p id="body-hint" class="hint">純文字，5000 字以內；換行會照原樣顯示。</p>
        <textarea id="body" name="body" rows="12" maxlength="5000" required
                  aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body', $item->body) }}</textarea>
        @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    @php($mode = old('publish', $item->published_at === null ? 'draft' : ($item->exists && $item->published_at->isFuture() ? 'at' : 'now')))
    <fieldset class="field">
        <legend>什麼時候發佈</legend>
        @foreach (['now' => $item->exists && $item->isPublished() ? '已發佈（改成現在的時間）' : '現在發佈', 'at' => '指定時間', 'draft' => '先存草稿（不公開）'] as $v => $label)
            <div class="field-check">
                <input id="publish-{{ $v }}" name="publish" type="radio" value="{{ $v }}" @checked($mode === $v)>
                <label for="publish-{{ $v }}">{{ $label }}</label>
            </div>
        @endforeach
        <label for="published_at">指定的發佈時間（選「指定時間」才要填）</label>
        <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $item->published_at?->shown()->format('Y-m-d\TH:i')) }}"
               @error('published_at') aria-invalid="true" aria-describedby="published_at-error" @enderror>
        @error('published_at')<p id="published_at-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>
    <button type="submit" class="button">存檔</button>
</form>
@endsection
