{{-- 一個下拉選單欄位。$name、$label、$options（[值 => 顯示] 或 [值,...]）、$value、$hint（可省略） --}}
@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $current = (string) old($name, $value ?? '');
    $desc = trim((isset($hint) && $hint ? $name . '-hint' : '') . ($errors->has($name) ? ' ' . $name . '-error' : ''));
@endphp
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    @if (isset($hint) && $hint)<p id="{{ $name }}-hint" class="hint">{{ $hint }}</p>@endif
    <select id="{{ $name }}" name="{{ $name }}" @if ($desc !== '') aria-describedby="{{ $desc }}" @endif @error($name) aria-invalid="true" @enderror>
        <option value="">請選擇</option>
        @foreach ($opts as $v => $text)
            <option value="{{ $v }}" @selected($current === (string) $v)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p id="{{ $name }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
