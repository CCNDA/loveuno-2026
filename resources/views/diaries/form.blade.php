@extends('layouts.app')

@section('title', $diary->exists ? '修改日記' : '寫日記')

@section('content')
<h1>{{ $diary->exists ? '修改日記' : '寫日記' }}</h1>

<form method="POST" action="{{ $diary->exists ? route('diaries.update', $diary) : route('diaries.store') }}" class="form form-wide" novalidate>
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
