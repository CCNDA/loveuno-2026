{{-- 送禮物表單：送禮物頁和個人首頁的「送禮物」對話框共用（熊叔叔 11/6 決定 #7）。
     $member、$items、$cost、$balance、$sentToday；$fieldPrefix（對話框用 g-，避免跟同一頁別的表單撞 id）、$inDialog --}}
@php
    $fp = $fieldPrefix ?? '';
    $useOld = ! ($inDialog ?? false) || old('_dialog') === 'gift'; // 對話框：只有這個對話框送出失敗時才帶回剛剛填的
@endphp
@if ($sentToday)
    <p class="notice" role="status">今天已經送過禮物給 {{ $member->name }} 了，明天再送吧。</p>
@endif

<form method="POST" action="{{ route('gifts.store', $member) }}" class="form form-wide" novalidate>
    @csrf
    @if ($inDialog ?? false)<input type="hidden" name="_dialog" value="gift">@endif
    @include('partials.errors', ['title' => '禮物還沒送出', 'fieldPrefix' => $fp])

    <fieldset id="{{ $fp }}item" tabindex="-1" class="field">
        <legend>請選擇禮物</legend>
        <ul class="gift-grid">
            @foreach ($items as $id => $name)
                <li>
                    <input id="{{ $fp }}item-{{ $id }}" name="item" type="radio" value="{{ $id }}" class="gift-radio"
                           @checked($useOld && (int) old('item') === $id)
                           @error('item') aria-invalid="true" aria-describedby="{{ $fp }}item-error" @enderror>
                    <label for="{{ $fp }}item-{{ $id }}" class="gift-card">
                        <img src="{{ \App\Models\Gift::imageUrl($id) }}" alt="" width="96" height="96" loading="lazy">
                        <span>{{ $name }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
        @error('item')<p id="{{ $fp }}item-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="field">
        <label for="{{ $fp }}message">簡訊（可以不寫）</label>
        <p id="{{ $fp }}message-hint" class="hint">您可以寫一句 20 個中文字以內的簡訊與禮物一起傳送。</p>
        <input id="{{ $fp }}message" name="message" type="text" maxlength="20" value="{{ $useOld ? old('message') : '' }}" data-count
               @if (! ($inDialog ?? false) && session('gift_edit')) autofocus @endif {{-- 從確認頁「回去修改」回來：游標直接在簡訊欄 --}}
               aria-describedby="{{ $fp }}message-hint {{ $fp }}message-count @error('message') {{ $fp }}message-error @enderror"
               @error('message') aria-invalid="true" @enderror>
        <p id="{{ $fp }}message-count" class="char-count" aria-live="off"><span>{{ mb_strlen($useOld ? old('message', '') : '') }}</span>/20</p>
        @error('message')<p id="{{ $fp }}message-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button">送出</button>
</form>
