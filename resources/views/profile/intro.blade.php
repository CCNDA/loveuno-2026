@extends('layouts.app')

@section('title', '自我介紹')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'intro'])
@include('photos.partials.photo-section')
<h2>自我介紹</h2>

<form method="POST" action="{{ route('profile.intro.update') }}" class="form form-wide intro-form" novalidate
      data-autosave="{{ route('profile.intro.patch') }}" data-autosave-groups="verse_book verse_chapter verse_verse;church_country church church_oid" data-autosave-together="verse_book verse_chapter verse_verse">
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '自我介紹還沒存好'])
    @php($O = \App\Support\ProfileOptions::class)

    <div class="field">
        <label for="headline">個人標題（必填）</label>
        <p id="headline-hint" class="hint">一句話介紹自己，會顯示在名字下面，例如「愛爬山的國小老師」。50 字以內。</p>
        <input id="headline" name="headline" type="text" maxlength="50" required value="{{ old('headline', $profile->headline) }}" data-count
               aria-describedby="headline-hint headline-count @error('headline') headline-error @enderror" @error('headline') aria-invalid="true" @enderror>
        <p id="headline-count" class="char-count" aria-live="off"><span>{{ mb_strlen(old('headline', $profile->headline ?? '')) }}</span>/50</p>
        @error('headline')<p id="headline-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    {{-- 設計稿 69:5836「我的教會(非必填)」：先選國家，教會名稱打字會列出名錄的教會（熊叔叔 11/6 決定 #11 甲）；找不到的可以自己打 --}}
    <fieldset class="field church-pick">
        <legend>我的教會（可以不填）</legend>
        <div class="church-row">
            @include('partials.select-field', ['name' => 'church_country', 'label' => '教會所在國家', 'options' => \App\Models\DirectoryChurch::countries(), 'value' => $profile->church_country ?? 'TW'])
            <div class="field">
                <label for="church">教會名稱</label>
                <p id="church-hint" class="hint">打幾個字，會列出名錄裡這個國家的教會；找不到也可以自己打名稱。</p>
                <input id="church" name="church" type="text" maxlength="100" list="church-list" autocomplete="off" data-search="{{ route('churches.search') }}"
                       value="{{ old('church', $profile->church) }}"
                       aria-describedby="church-hint @error('church') church-error @enderror" @error('church') aria-invalid="true" @enderror>
                <datalist id="church-list"></datalist>
                <input type="hidden" id="church_oid" name="church_oid" value="{{ old('church_oid', $profile->church_oid) }}">
                @error('church')<p id="church-error" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>
    <div class="field field-check">
        {{-- 送出失敗回來時，沒勾的 checkbox 不會出現在 old()，要用這次送的值，不是資料庫的舊值 --}}
        <input id="church_visible" name="church_visible" type="checkbox" value="1" @checked($errors->any() ? old('church_visible') : $profile->church_visible)>
        <label for="church_visible">在我的交友檔案中顯示我的教會</label>
    </div>

    <fieldset class="field">
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
        {{-- 經文自己帶出來，不讓人打（熊叔叔 11/5）：選好書卷、章、節，下面就出現和合本的經文 --}}
        <p class="verse-preview" id="verse-preview" aria-live="polite" data-url="{{ route('bible.verse') }}">{{ $profile->verse_text !== '' ? $profile->verse_text : '選好書卷、章、節，這裡會帶出經文。' }}</p>
        <script src="{{ asset('js/bible-verse.js') }}?v={{ $appVersion }}" defer></script>
    </fieldset>

    @foreach ([['languages', '慣用語言（必填）', '請選擇 1～3 種慣用的語言。', $O::LANGUAGES], ['hobbies', '興趣（必填）', '請選擇 1～3 種興趣嗜好。', $O::HOBBIES], ['sports', '喜愛的運動', '請選擇 1～3 種喜愛的體育活動（可以不選）。', $O::SPORTS]] as [$key, $label, $hint, $options])
        @php($chosen = $errors->any() ? (array) old($key, []) : ($profile->{$key} ?? []))
        @php($groupError = $errors->first($key) ?: $errors->first($key . '.*'))
        <fieldset class="field" id="{{ $key }}" tabindex="-1" data-tags="{{ $O::MAX_PICKS }}" aria-describedby="{{ $key }}-hint{{ $groupError ? ' ' . $key . '-error' : '' }}">
            <legend>{{ $label }}</legend>
            <p id="{{ $key }}-hint" class="hint">{{ $hint }}</p>
            <div class="check-grid chip-checks">
                @foreach ($options as $i => $opt)
                    <div class="field-check">
                        <input id="{{ $key }}-{{ $i }}" name="{{ $key }}[]" type="checkbox" value="{{ $opt }}" @checked(in_array($opt, $chosen, true))>
                        <label for="{{ $key }}-{{ $i }}">{{ $opt }}</label>
                    </div>
                @endforeach
            </div>
            @if ($groupError)<p id="{{ $key }}-error" class="field-error">{{ $groupError }}</p>@endif
        </fieldset>
    @endforeach

    <script src="{{ asset('js/church-pick.js') }}?v={{ $appVersion }}" defer></script>
    <script src="{{ asset('js/tag-picker.js') }}?v={{ $appVersion }}" defer></script>

    @foreach ([['intro', '自我介紹（必填）', 2000, $profile->intro, '請簡短的寫一篇 30 字以上的自我介紹。'], ['hope', '期待認識的人', 1000, $profile->hope, '可以不填。']] as [$key, $label, $max, $value, $extra])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            <p id="{{ $key }}-hint" class="hint">{{ $extra }}最多 {{ $max }} 字。請不要寫電話、住址等聯絡方式。</p>
            <textarea id="{{ $key }}" name="{{ $key }}" rows="6" maxlength="{{ $max }}"
                      aria-describedby="{{ trim($key . '-hint' . ($errors->has($key) ? ' ' . $key . '-error' : '')) }}"
                      @error($key) aria-invalid="true" @enderror>{{ old($key, $value) }}</textarea>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <button type="submit" class="button pill-submit">存檔</button>
</form>

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
<script src="{{ asset('js/char-count.js') }}?v={{ $appVersion }}" defer></script>
@endsection
