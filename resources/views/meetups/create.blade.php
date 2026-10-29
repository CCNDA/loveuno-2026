@extends('layouts.app')

@section('title', '約會')

@section('content')
{{-- 設計稿 AskOutDialog（對話框改成一頁，跟送禮物一樣）：邀請你(妳)於、時間、地點、活動類型、邀請對象、想說的話 --}}
<h1>邀請 {{ $member->name }} 約會</h1>
@if ($level < 2)
    <p class="notice" role="status">要先跟 {{ $member->name }} 成為「聊天好友」才能邀請約會。</p>
    <p>交友三步驟：先互送禮物成為「普通好友」，再互傳紙條各 {{ \App\Models\Friendship::CHAT_NOTES }} 則以上成為「聊天好友」，就可以邀請對方在教會的活動見面。</p>
    <p><a class="button" href="{{ route('notes.show', $member) }}">傳紙條給 {{ $member->name }}</a></p>
@else
    <p>邀請一次 {{ $cost }} 點（您現在有 {{ $balance }} 點），對方婉拒或取消都不會退。為了安全，請約在教會的活動見面，不要隨便喝陌生人給的飲料。</p>
    <form method="POST" action="{{ route('meetups.store', $member) }}" class="form form-wide" novalidate>
        @csrf
        @include('partials.errors', ['title' => '約會邀請還沒送出'])
        <div class="field">
            <label for="meet_on">邀請你(妳)於（日期）</label>
            <input id="meet_on" name="meet_on" type="date" min="{{ today()->addDay()->toDateString() }}" max="{{ today()->addYear()->subDay()->toDateString() }}" value="{{ old('meet_on') }}" required
                   @error('meet_on') aria-invalid="true" aria-describedby="meet_on-error" @enderror>
            @error('meet_on')<p id="meet_on-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('partials.select-field', ['name' => 'meet_time', 'label' => '時間', 'options' => \App\Http\Controllers\MeetupController::times(), 'value' => old('meet_time', '10:00')])
        <div class="field">
            <label for="place">地點</label>
            <p id="place-hint" class="hint">教會名稱和地址，100 字以內。</p>
            <input id="place" name="place" type="text" maxlength="100" value="{{ old('place') }}" required
                   aria-describedby="place-hint @error('place') place-error @enderror" @error('place') aria-invalid="true" @enderror>
            @error('place')<p id="place-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('partials.select-field', ['name' => 'activity', 'label' => '活動類型', 'options' => \App\Models\Meetup::ACTIVITIES, 'value' => old('activity')])
        @include('partials.select-field', ['name' => 'company', 'label' => '邀請對象', 'options' => \App\Models\Meetup::COMPANY, 'value' => old('company')])
        <div class="field">
            <label for="message">除了以上制式的約會邀請，您還可以為這次約會多寫幾句話（可以不寫）</label>
            <textarea id="message" name="message" rows="6" maxlength="1000"
                      @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
            @error('message')<p id="message-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">送出</button>
    </form>
@endif
@endsection
