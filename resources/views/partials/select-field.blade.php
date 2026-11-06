{{-- 一個下拉選單欄位。$name、$label、$options（[值 => 顯示] 或 [值,...]）、$value、$hint（可省略）、$fieldPrefix（id 前綴，可省略） --}}
@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $current = (string) old($name, $value ?? '');
    $fid = ($fieldPrefix ?? '') . $name;
    $desc = trim((isset($hint) && $hint ? $fid . '-hint' : '') . ($errors->has($name) ? ' ' . $fid . '-error' : ''));
@endphp
<div class="field">
    <label for="{{ $fid }}">{{ $label }}</label>
    @if (isset($hint) && $hint)<p id="{{ $fid }}-hint" class="hint">{{ $hint }}</p>@endif
    <select id="{{ $fid }}" name="{{ $name }}" @if (! empty($required)) required @endif @if ($desc !== '') aria-describedby="{{ $desc }}" @endif @error($name) aria-invalid="true" @enderror>
        <option value="">請選擇</option>
        @foreach ($opts as $v => $text)
            <option value="{{ $v }}" @selected($current === (string) $v)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p id="{{ $fid }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
