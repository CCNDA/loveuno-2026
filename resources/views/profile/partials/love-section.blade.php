{{-- 戀愛履歷（熊叔叔 11/6：照設計個人檔案只有 4 個子頁，戀愛履歷搬到個人資料頁下半；愛積點任務「完成戀愛履歷建檔」不變） --}}
@php($loveAnswers = auth()->user()->profile?->love_history ?? [])
<section id="love" class="love-section" aria-labelledby="love-title">
<h2 id="love-title">戀愛履歷</h2>
<p class="hint">選擇題都要選（不想說的，大多可以選「保留」或「無」）；兩題文字題可以不寫。填完可以得到 10 點愛諾積點。</p>

<form method="POST" action="{{ route('profile.love.update') }}" class="form form-wide" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '戀愛履歷還沒存好'])

    @foreach (\App\Support\LoveHistory::ORDER as $key)
        @if (isset(\App\Support\LoveHistory::QUESTIONS[$key]))
            @php([$label, $options] = \App\Support\LoveHistory::QUESTIONS[$key])
            @include('partials.select-field', ['name' => $key, 'label' => $label . '（必填）', 'options' => $options, 'value' => $loveAnswers[$key] ?? '', 'required' => true])
            @continue
        @endif
        @php([$label, $max] = \App\Support\LoveHistory::TEXTS[$key])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}（可以不寫）</label>
            <p id="{{ $key }}-hint" class="hint">{{ $max }} 字以內。</p>
            <textarea id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="{{ $max }}"
                      aria-describedby="{{ $key }}-hint @error($key) {{ $key }}-error @enderror"
                      @error($key) aria-invalid="true" @enderror>{{ old($key, $loveAnswers[$key] ?? '') }}</textarea>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <button type="submit" class="button">存檔</button>
</form>
</section>
