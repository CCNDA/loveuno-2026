{{-- 頁首通知鈴＋點開的浮動框（設計稿 Info_Description 819:11123／手機 221:18610；熊叔叔 2026-11-05 TG「右上角通知可以點開浮動的資訊」）。
     用 <details>：沒有 JS 也能開關；js/notifications.js 補上點外面、按 Esc 關。資料由 App\Support\Notifications 給。 --}}
@php
    $notifs = \App\Support\Notifications::for(auth()->user());
    $notifCount = \App\Support\Notifications::badgeCount(auth()->user());
@endphp
<details class="notif">
    <summary class="header-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z M10 21h4" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"/></svg>
        <span class="visually-hidden">通知</span>@if($notifCount > 0)<span class="badge header-badge" aria-hidden="true">{{ $notifCount }}</span><span class="visually-hidden">，有 {{ $notifCount }} 則新通知</span>@endif
    </summary>
    <div class="notif-panel">
        <h2 class="notif-title">新通知</h2>
        @if (empty($notifs))
            <p class="notif-empty">最近兩週沒有新通知。</p>
        @else
            <ul class="notif-list">
                @foreach ($notifs as $n)
                    <li class="notif-item">
                        @if ($n['user'])
                            @include('partials.avatar', ['user' => $n['user'], 'size' => 'sm'])
                        @else
                            <span class="notif-icon" aria-hidden="true">i</span>
                        @endif
                        <div class="notif-main">
                            <p>@if ($n['kind'] === 'news')<strong>系統公告</strong><br>@endif{{ $n['text'] }}
                                <a href="{{ $n['url'] }}">{{ $n['action'] }}</a></p>
                            <p class="notif-time"><time datetime="{{ $n['at']->toIso8601String() }}">{{ \App\Support\Notifications::ago($n['at']) }}</time></p>
                        </div>
                        @if ($n['unread'])<span class="notif-dot" aria-hidden="true"></span><span class="visually-hidden">（新）</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</details>
