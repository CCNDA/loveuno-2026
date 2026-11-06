{{-- 約會邀請表單：約會頁和個人首頁的「約會」對話框共用（熊叔叔 11/6 決定 #7）。
     $member、$level、$cost、$balance；$fieldPrefix（對話框用 d-）、$inDialog --}}
@php
    $fp = $fieldPrefix ?? '';
    $useOld = ! ($inDialog ?? false) || old('_dialog') === 'date';
@endphp
@if ($level < 2)
    <p class="notice" role="status">要先跟 {{ $member->name }} 成為「聊天好友」才能邀請約會。</p>
    <p>交友三步驟：先互送禮物成為「普通好友」，再互傳紙條各 {{ \App\Models\Friendship::CHAT_NOTES }} 則以上成為「聊天好友」，就可以邀請對方在教會的活動見面。</p>
    @if ($level >= 1)
        <p><a class="button" href="{{ route('notes.show', $member) }}">傳紙條給 {{ $member->name }}</a></p>
    @else
        <p><a class="button" href="{{ route('gifts.create', $member) }}" @if ($inDialog ?? false) data-dialog="gift" @endif>送禮物給 {{ $member->name }}</a></p>
    @endif
@else
    <p>邀請一次 {{ $cost }} 點（您現在有 {{ $balance }} 點），對方婉拒或取消都不會退。為了安全，請約在教會的活動見面，不要隨便喝陌生人給的飲料。</p>
    <form id="{{ $fp }}meetup" tabindex="-1" method="POST" action="{{ route('meetups.store', $member) }}" class="form form-wide" novalidate>
        @csrf
        @if ($inDialog ?? false)<input type="hidden" name="_dialog" value="date">@endif
        @include('partials.errors', ['title' => '約會邀請還沒送出', 'fieldPrefix' => $fp])
        <div class="field">
            <label for="{{ $fp }}meet_on">邀請你(妳)於（日期）</label>
            <input id="{{ $fp }}meet_on" name="meet_on" type="date" min="{{ today()->addDay()->toDateString() }}" max="{{ today()->addYear()->subDay()->toDateString() }}" value="{{ $useOld ? old('meet_on') : '' }}" required
                   @error('meet_on') aria-invalid="true" aria-describedby="{{ $fp }}meet_on-error" @enderror>
            @error('meet_on')<p id="{{ $fp }}meet_on-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('partials.select-field', ['name' => 'meet_time', 'label' => '時間', 'options' => \App\Http\Controllers\MeetupController::times(), 'value' => $useOld ? old('meet_time', '10:00') : '10:00', 'fieldPrefix' => $fp])
        <div class="field">
            <label for="{{ $fp }}place">地點</label>
            <p id="{{ $fp }}place-hint" class="hint">教會名稱和地址，100 字以內。</p>
            <input id="{{ $fp }}place" name="place" type="text" maxlength="100" value="{{ $useOld ? old('place') : '' }}" required
                   aria-describedby="{{ $fp }}place-hint @error('place') {{ $fp }}place-error @enderror" @error('place') aria-invalid="true" @enderror>
            @error('place')<p id="{{ $fp }}place-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('partials.select-field', ['name' => 'activity', 'label' => '活動類型', 'options' => \App\Models\Meetup::ACTIVITIES, 'value' => $useOld ? old('activity') : null, 'fieldPrefix' => $fp])
        @include('partials.select-field', ['name' => 'company', 'label' => '邀請對象', 'options' => \App\Models\Meetup::COMPANY, 'value' => $useOld ? old('company') : null, 'fieldPrefix' => $fp])
        <div class="field">
            <label for="{{ $fp }}message">除了以上制式的約會邀請，您還可以為這次約會多寫幾句話</label>
            <textarea id="{{ $fp }}message" name="message" rows="6" maxlength="1000" required
                      @error('message') aria-invalid="true" aria-describedby="{{ $fp }}message-error" @enderror>{{ $useOld ? old('message') : '' }}</textarea>
            @error('message')<p id="{{ $fp }}message-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">送出</button>
    </form>
@endif
