{{-- 交友條件的「從～到」一組（設計稿：兩格中間一個 ~）。$k＝age／height／weight --}}
<fieldset id="pref_{{ $k }}_min" tabindex="-1" class="field">
    <legend>{{ $label }}</legend>
    <div class="age-range">
        <label for="pref_{{ $k }}_min_input" class="visually-hidden">{{ $label }}下限</label>
        <input id="pref_{{ $k }}_min_input" name="pref_{{ $k }}_min" type="number" inputmode="numeric" min="{{ $lo }}" max="{{ $hi }}"
               value="{{ old('pref_' . $k . '_min', $profile->{'pref_' . $k . '_min'}) }}"
               @error('pref_' . $k . '_min') aria-invalid="true" aria-describedby="pref_{{ $k }}_min-error" @enderror>
        @if ($unit)<span aria-hidden="true">{{ $unit }}</span>@endif
        <span aria-hidden="true">～</span>
        <label for="pref_{{ $k }}_max" class="visually-hidden">{{ $label }}上限</label>
        <input id="pref_{{ $k }}_max" name="pref_{{ $k }}_max" type="number" inputmode="numeric" min="{{ $lo }}" max="{{ $hi }}"
               value="{{ old('pref_' . $k . '_max', $profile->{'pref_' . $k . '_max'}) }}"
               @error('pref_' . $k . '_max') aria-invalid="true" aria-describedby="pref_{{ $k }}_max-error" @enderror>
        @if ($unit)<span aria-hidden="true">{{ $unit }}</span>@endif
    </div>
    @error('pref_' . $k . '_min')<p id="pref_{{ $k }}_min-error" class="field-error">{{ $message }}</p>@enderror
    @error('pref_' . $k . '_max')<p id="pref_{{ $k }}_max-error" class="field-error">{{ $message }}</p>@enderror
</fieldset>
