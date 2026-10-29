@extends('layouts.app')

@section('title', '自我介紹')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'intro'])
<h2>自我介紹</h2>

<form method="POST" action="{{ route('profile.intro.update') }}" class="form form-wide" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '自我介紹還沒存好'])
    @php($O = \App\Support\ProfileOptions::class)

    <div class="field">
        <label for="headline">個人標題</label>
        <p id="headline-hint" class="hint">一句話介紹自己，例如「愛爬山的國小老師」。30 字以內。</p>
        <input id="headline" name="headline" type="text" maxlength="30" value="{{ old('headline', $profile->headline) }}"
               aria-describedby="headline-hint @error('headline') headline-error @enderror" @error('headline') aria-invalid="true" @enderror>
        @error('headline')<p id="headline-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="church">我的教會（教會名稱）</label>
        <input id="church" name="church" type="text" maxlength="100" value="{{ old('church', $profile->church) }}"
               @error('church') aria-invalid="true" aria-describedby="church-error" @enderror>
        @error('church')<p id="church-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-check">
        {{-- 送出失敗回來時，沒勾的 checkbox 不會出現在 old()，要用這次送的值，不是資料庫的舊值 --}}
        <input id="church_visible" name="church_visible" type="checkbox" value="1" @checked($errors->any() ? old('church_visible') : $profile->church_visible)>
        <label for="church_visible">在我的交友檔案中顯示我的教會</label>
    </div>

    <fieldset class="field" id="verse_book-group">
        <legend>最愛經文（可以不填）</legend>
        <div class="verse-row">
            @include('partials.select-field', ['name' => 'verse_book', 'label' => '聖經書卷', 'options' => $O::BIBLE_BOOKS, 'value' => $profile->verse_book])
            @foreach ([['verse_chapter', '章', $profile->verse_chapter], ['verse_verse', '節', $profile->verse_verse]] as [$key, $label, $value])
                <div class="field">
                    <label for="{{ $key }}">{{ $label }}</label>
                    <input id="{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" min="1" value="{{ old($key, $value) }}" class="input-short"
                           @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror>
                    @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="field">
            <label for="verse_text">經文</label>
            <textarea id="verse_text" name="verse_text" rows="3" maxlength="300" @error('verse_text') aria-invalid="true" aria-describedby="verse_text-error" @enderror>{{ old('verse_text', $profile->verse_text) }}</textarea>
            @error('verse_text')<p id="verse_text-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    @foreach ([['languages', '慣用語言', '請選擇 1～3 種慣用的語言。', $O::LANGUAGES], ['hobbies', '興趣', '請選擇 1～3 種興趣嗜好。', $O::HOBBIES], ['sports', '喜愛的運動', '請選擇 1～3 種喜愛的體育活動。', $O::SPORTS]] as [$key, $label, $hint, $options])
        @php($chosen = $errors->any() ? (array) old($key, []) : ($profile->{$key} ?? []))
        <fieldset class="field" id="{{ $key }}" tabindex="-1" aria-describedby="{{ $key }}-hint @error($key) {{ $key }}-error @enderror">
            <legend>{{ $label }}</legend>
            <p id="{{ $key }}-hint" class="hint">{{ $hint }}</p>
            <div class="check-grid">
                @foreach ($options as $i => $opt)
                    <div class="field-check">
                        <input id="{{ $key }}-{{ $i }}" name="{{ $key }}[]" type="checkbox" value="{{ $opt }}" @checked(in_array($opt, $chosen, true))>
                        <label for="{{ $key }}-{{ $i }}">{{ $opt }}</label>
                    </div>
                @endforeach
            </div>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </fieldset>
    @endforeach

    @foreach ([['intro', '自我介紹', 2000, $profile->intro, '請簡短的寫一篇 30 字以上的自我介紹。'], ['hope', '期待認識的人', 1000, $profile->hope, null]] as [$key, $label, $max, $value, $extra])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            <p id="{{ $key }}-hint" class="hint">{{ $extra }}最多 {{ $max }} 字。請不要寫電話、住址等聯絡方式。</p>
            <textarea id="{{ $key }}" name="{{ $key }}" rows="6" maxlength="{{ $max }}"
                      aria-describedby="{{ trim($key . '-hint' . ($errors->has($key) ? ' ' . $key . '-error' : '')) }}"
                      @error($key) aria-invalid="true" @enderror>{{ old($key, $value) }}</textarea>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <button type="submit" class="button">存檔</button>
</form>

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
@endsection
