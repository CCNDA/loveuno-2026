{{-- 寫日記的欄位（設計稿 寫日記/需要代禱 235:8504：類型、標題、內文、照片上傳）。寫日記頁和首頁跳出的對話框共用。
     $diary；$px：id 前綴（對話框用 w-，避免跟頁面上其他欄位撞名；出錯時會回到寫日記頁，錯誤連結對得到沒前綴的 id） --}}
@php($px = $px ?? '')
<div class="field">
    <label for="{{ $px }}is_prayer">類型</label>
    <select id="{{ $px }}is_prayer" name="is_prayer" aria-describedby="{{ $px }}is_prayer-hint">
        <option value="0" @selected(! old('is_prayer', $diary->is_prayer))>心情日記</option>
        <option value="1" @selected((bool) old('is_prayer', $diary->is_prayer))>需要代禱</option>
    </select>
    <p id="{{ $px }}is_prayer-hint" class="hint">選「需要代禱」，日記上會有「代禱」標籤，大家可以按「為你禱告」。</p>
</div>

<div class="field">
    <label for="{{ $px }}title">標題</label>
    <input id="{{ $px }}title" name="title" type="text" maxlength="150" value="{{ old('title', $diary->title) }}" required data-count
           aria-describedby="{{ $px }}title-count @error('title') title-error @enderror" @error('title') aria-invalid="true" @enderror>
    <p id="{{ $px }}title-count" class="char-count" aria-live="off"><span>{{ mb_strlen(old('title', $diary->title ?? '')) }}</span>/150</p>
    @error('title')<p id="title-error" class="field-error">{{ $message }}</p>@enderror
</div>

<div class="field">
    <label for="{{ $px }}body">內文</label>
    <textarea id="{{ $px }}body" name="body" rows="{{ $px ? 6 : 12 }}" maxlength="10000" required placeholder="今天想寫些什麼" data-count
              aria-describedby="{{ $px }}body-count @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body', $diary->body) }}</textarea>
    <p id="{{ $px }}body-count" class="char-count" aria-live="off"><span>{{ mb_strlen(old('body', $diary->body ?? '')) }}</span>/10000</p>
    @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
</div>

<div class="field">
    <label for="{{ $px }}image">照片上傳<span class="hint">（可以不放）</span></label>
    {{-- 整個框就是按鈕：點了開檔案總管（熊叔叔 11/5「多一個選擇檔案很難看」）；選好會在框裡預覽 --}}
    <input id="{{ $px }}image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="camera-input photo-pick"
           aria-describedby="{{ $px }}image-hint @error('image') image-error @enderror" @error('image') aria-invalid="true" @enderror>
    <label for="{{ $px }}image" class="camera-box photo-box"><img class="photo-box-icon" src="{{ asset('images/brand/camera.svg') }}" alt="" width="32" height="32"><span class="camera-text">點這裡選照片</span></label>
    <p id="{{ $px }}image-hint" class="hint">檔案格式：JPG、PNG、WebP，單一檔案不超過 5MB。看得到這篇日記的會員都看得到照片。</p>
    @error('image')<p id="image-error" class="field-error">{{ $message }}</p>@enderror
</div>
