{{-- 誰看得到（設計稿沒有，舊站有「只有自己看得到」，保留）。$px：id 前綴 --}}
@php($px = $px ?? '')
<div class="field">
    <label for="{{ $px }}visibility">誰看得到</label>
    <select id="{{ $px }}visibility" name="visibility" @error('visibility') aria-invalid="true" aria-describedby="visibility-error" @enderror>
        <option value="public" @selected(old('visibility', $diary->visibility) === 'public')>所有登入的會員</option>
        <option value="private" @selected(old('visibility', $diary->visibility) === 'private')>只有我自己</option>
    </select>
    @error('visibility')<p id="visibility-error" class="field-error">{{ $message }}</p>@enderror
</div>
