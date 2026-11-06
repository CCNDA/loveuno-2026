{{-- 個人首頁上跳出的「送禮物」「相約見面」「傳情書」對話框（熊叔叔 11/6 決定 #7；設計稿 328:7030、342:51697、235:7660）。
     按鈕本來就是連到各自的頁面：有 JS、瀏覽器有 <dialog> 才改成在這頁跳出（member-dialogs.js），沒有就照舊換頁。
     送出失敗會回到這頁：只有送出的那個對話框帶錯誤、自動再打開（_dialog）；別的對話框用空的錯誤袋，不會跟著顯示錯誤。
     $member、$dialogs（MemberController::show 給的：points、giftCost、dateCost、level、sentToday、canNote） --}}
@php
    $which = $errors->any() ? old('_dialog') : null;
    $noErrors = new \Illuminate\Support\ViewErrorBag;
@endphp

<dialog id="dlg-gift" class="member-dialog airmail" aria-labelledby="dlg-gift-title" @if ($which === 'gift') data-open @endif>
    <div class="member-dialog-head">
        <h2 id="dlg-gift-title">送禮物給 {{ $member->name }}</h2>
        <button type="button" class="dialog-close" data-close aria-label="關閉">✕</button>
    </div>
    <ul class="member-dialog-notes">
        <li>送一份禮物給陌生網友是對他(她)表達好感的第一步。</li>
        <li>若對方回送給您一個禮物，雙方就成為「普通好友」，可以開始互傳紙條。</li>
        <li>每天只可送一份禮物給同一個會員。</li>
        <li>送禮物需要使用 {{ $dialogs['giftCost'] }} 點愛諾積點。您的積點餘額：{{ $dialogs['points'] }}。</li>
    </ul>
    @include('gifts.partials.form', ['items' => \App\Models\Gift::ITEMS, 'cost' => $dialogs['giftCost'], 'balance' => $dialogs['points'],
        'sentToday' => $dialogs['sentToday'], 'fieldPrefix' => 'g-', 'inDialog' => true, 'errors' => $which === 'gift' ? $errors : $noErrors])
</dialog>

<dialog id="dlg-date" class="member-dialog airmail" aria-labelledby="dlg-date-title" @if ($which === 'date') data-open @endif>
    <div class="member-dialog-head">
        <h2 id="dlg-date-title">相約見面：{{ $member->name }}</h2>
        <button type="button" class="dialog-close" data-close aria-label="關閉">✕</button>
    </div>
    <ul class="member-dialog-notes">
        <li>為了約會安全，您只可以約對方參加教會舉辦的活動。</li>
        <li>請先不要留下您的手機號碼；若對方同意見面，再用紙條交換手機號碼。</li>
        <li>請注意禮節，提出約會邀約若非突發狀況，請一定要準時參加。</li>
    </ul>
    @include('meetups.partials.form', ['level' => $dialogs['level'], 'cost' => $dialogs['dateCost'], 'balance' => $dialogs['points'],
        'fieldPrefix' => 'd-', 'inDialog' => true, 'errors' => $which === 'date' ? $errors : $noErrors])
</dialog>

@if ($dialogs['canNote'])
    @php($ne = $which === 'note' ? $errors : $noErrors)
    @php($useOld = $which === 'note')
    <dialog id="dlg-note" class="member-dialog airmail" aria-labelledby="dlg-note-title" @if ($which === 'note') data-open @endif>
        <div class="member-dialog-head">
            <h2 id="dlg-note-title">傳情書給 {{ $member->name }}</h2>
            <button type="button" class="dialog-close" data-close aria-label="關閉">✕</button>
        </div>
        <form method="POST" action="{{ route('notes.store', $member) }}" class="form form-wide" novalidate>
            @csrf
            <input type="hidden" name="_dialog" value="note">
            @include('partials.errors', ['title' => '情書沒有送出', 'fieldPrefix' => 'n-', 'errors' => $ne])
            <p class="member-dialog-to">收件人：{{ $member->name }}</p>
            <div class="field">
                <label for="n-title">標題</label>
                <input id="n-title" name="title" type="text" maxlength="20" value="{{ $useOld ? old('title') : '' }}" data-count
                       aria-describedby="n-title-count @if ($ne->has('title')) n-title-error @endif" @if ($ne->has('title')) aria-invalid="true" @endif>
                <p id="n-title-count" class="char-count" aria-live="off"><span>{{ mb_strlen($useOld ? old('title', '') : '') }}</span>/20</p>
                @if ($ne->has('title'))<p id="n-title-error" class="field-error">{{ $ne->first('title') }}</p>@endif
            </div>
            <div class="field">
                <label for="n-body">內文</label>
                <p id="n-body-hint" class="hint">最多 1000 字。請不要在情書裡留電話、住址等聯絡方式。</p>
                <textarea id="n-body" name="body" rows="5" maxlength="1000" required data-count
                          aria-describedby="n-body-hint n-body-count @if ($ne->has('body')) n-body-error @endif" @if ($ne->has('body')) aria-invalid="true" @endif>{{ $useOld ? old('body') : '' }}</textarea>
                <p id="n-body-count" class="char-count" aria-live="off"><span>{{ mb_strlen($useOld ? old('body', '') : '') }}</span>/1000</p>
                @if ($ne->has('body'))<p id="n-body-error" class="field-error">{{ $ne->first('body') }}</p>@endif
            </div>
            <button type="submit" class="button">送出</button>
        </form>
    </dialog>
@endif
<script src="{{ asset('js/char-count.js') }}?v={{ $appVersion }}" defer></script>
<script src="{{ asset('js/member-dialogs.js') }}?v={{ $appVersion }}" defer></script>
