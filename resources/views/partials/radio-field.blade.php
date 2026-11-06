{{-- 一組圓點單選欄位（跟 select-field 同樣的參數）。$name、$label、$options（[值 => 顯示] 或 [值,...]）、$value、$hint、$required（可省略）
     熊叔叔 11/6 決定：個人資料的「信仰」「計劃生育」照設計稿 69:5636 改圓點 --}}
@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $current = (string) old($name, $value ?? '');
    $desc = trim((isset($hint) && $hint ? $name . '-hint' : '') . ($errors->has($name) ? ' ' . $name . '-error' : ''));
@endphp
<fieldset id="{{ $name }}" tabindex="-1" class="field radio-field" @if ($desc !== '') aria-describedby="{{ $desc }}" @endif>
    <legend>{{ $label }}</legend>
    @if (isset($hint) && $hint)<p id="{{ $name }}-hint" class="hint">{{ $hint }}</p>@endif
    <div class="radio-row">
        @foreach ($opts as $v => $text)
            <span class="radio-opt">
                <input id="{{ $name }}-{{ $loop->index }}" name="{{ $name }}" type="radio" value="{{ $v }}" @checked($current === (string) $v)
                       @if (! empty($required)) required @endif @error($name) aria-invalid="true" @enderror>
                <label for="{{ $name }}-{{ $loop->index }}">{{ $text }}</label>
            </span>
        @endforeach
    </div>
    @error($name)<p id="{{ $name }}-error" class="field-error">{{ $message }}</p>@enderror
</fieldset>
