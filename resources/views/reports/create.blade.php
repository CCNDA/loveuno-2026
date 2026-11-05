@extends('layouts.app')

@section('title', '檢舉會員')

@section('main_class', 'wrap wrap-wide center-main page-card')
@section('content')
<h1>檢舉會員的不當行為</h1>
<p class="prose">愛諾園是一個屬神的交友空間，而這樣一個空間的秩序需要您協助我們一起來維持。若您發現有任何不適當的行為，包含在個人檔案、日記或是任何社群留言中撰寫暴力、援交、色情、異端或是商業廣告內容，請您告訴我們，讓我們可以做適當的處理。（所有檢舉內容我們將完全保密，請您放心）</p>
<p>要檢舉的會員：<strong>{{ $member->name }}</strong></p>

<form method="POST" action="{{ route('reports.store', $member) }}" class="form form-wide report-form" enctype="multipart/form-data" novalidate>
    @csrf
    @include('partials.errors', ['title' => '檢舉還沒送出'])

    <div class="field">
        <label for="description">內容描述</label>
        <p id="description-hint" class="hint">發生了什麼事、在哪裡看到的（例如哪一篇日記、哪一天的紙條），500 字以內。</p>
        <textarea id="description" name="description" rows="6" maxlength="500" required
                  aria-describedby="description-hint @error('description') description-error @enderror"
                  @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
        @error('description')<p id="description-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="reason">檢舉原因</label>
        <select id="reason" name="reason" required
                @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>
            <option value="">請選擇檢舉原因</option>
            @foreach ($reasons as $value => $label)
                <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('reason')<p id="reason-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="screenshot">圖片上傳（可以不放）</label>
        <p id="screenshot-hint" class="hint">截圖之類的證據。檔案格式：JPG、PNG，單一檔案不超過 5MB。只有管理員看得到。</p>
        <input id="screenshot" name="screenshot" type="file" accept="image/jpeg,image/png"
               aria-describedby="screenshot-hint @error('screenshot') screenshot-error @enderror"
               @error('screenshot') aria-invalid="true" @enderror>
        @error('screenshot')<p id="screenshot-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button">送出</button>
</form>
<p><a href="{{ route('members.show', $member) }}">← 回 {{ $member->name }} 的檔案</a></p>
@endsection
