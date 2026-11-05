{{-- 個人首頁上面那張白卡（設計稿 Profile 142:22363；熊叔叔 2026-11-05 TG「每個個人的首頁會輪播 slide」「個人帳號的封面圖應該是有做」）：
     上：輪播（自己上傳、看得到的封面排第一張，接著設計稿三張 slide 69:3461／69:3583／69:4933）
     下：大頭照疊在輪播下緣、暱稱、一句話、居住地／年齡、認證盾牌、最近登入／更新；右邊與您關係、解除、檢舉、傳紙條／送禮物／約會。
     $member、$isMe、$iBlocked --}}
@php
    $cp = $member->profile;
    $blocked = $iBlocked ?? false;
    $hasCover = ! $blocked && $cp && $cp->coverVisibleTo(auth()->user());
    $slides = [
        ['img' => 'slide-1.jpg', 'alt' => '跨出教會聯誼，發現另一半：認識合拍屬靈伴侶、尋找交往對象'],
        ['img' => 'slide-2.jpg', 'alt' => '嚴謹認證，安心交友：多重認證機制，創造優質交友環境'],
        ['img' => 'slide-3.jpg', 'alt' => '交通團契，活出信仰：透過日記分享生活見證，互相代禱、彼此關心'],
    ];
    $total = count($slides) + ($hasCover ? 1 : 0);
@endphp
<section class="card profile-card" aria-labelledby="member-name">
    <div class="slides" aria-roledescription="輪播" aria-label="封面">
        <div class="slides-track">
            @if ($hasCover)
                <div class="slide" role="group" aria-roledescription="投影片" aria-label="1 / {{ $total }}">
                    <img src="{{ route('covers.show', $member) }}?v={{ $cp->cover_updated_at?->timestamp }}" alt="{{ $member->name }} 的封面" width="1200" height="400">
                </div>
            @endif
            @foreach ($slides as $i => $s)
                <div class="slide" role="group" aria-roledescription="投影片" aria-label="{{ $i + 1 + ($hasCover ? 1 : 0) }} / {{ $total }}">
                    <img src="{{ asset('images/slides/' . $s['img']) }}" alt="{{ $s['alt'] }}" width="1750" height="556" loading="{{ $i === 0 && ! $hasCover ? 'eager' : 'lazy' }}">
                </div>
            @endforeach
        </div>
        {{-- 按鈕由 js/slides.js 打開（沒有 JS 只看第一張）；會自己換，可以暫停（WCAG 2.2.2） --}}
        <div class="slides-controls" hidden>
            <script src="{{ asset('js/slides.js') }}?v={{ $appVersion }}" defer></script>
            <button type="button" class="slides-pause" aria-pressed="false">暫停輪播</button>
            @for ($i = 1; $i <= $total; $i++)
                <button type="button" class="slides-dot" aria-label="看第 {{ $i }} 張"></button>
            @endfor
        </div>
    </div>

    <div class="profile-row">
        <div class="profile-avatar">@include('partials.avatar', ['user' => $member, 'size' => 'xl'])</div>
        <div class="profile-main">
            @php($approved = $blocked ? collect() : $member->approvedVerifications())
            <h1 id="member-name">{{ $member->name }}@if ($approved->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="20" height="20" class="verified-badge">@endif @if($isMe) <span class="tag">我</span>@endif</h1>
            @if (! $blocked)
                @if (($cp?->headline ?? '') !== '')<p class="headline">{{ $cp->headline }}</p>@endif
                @php($where = collect([$cp?->live_place, $cp?->ageGroup()])->filter()->implode(' / '))
                @if ($where !== '')<p class="profile-where"><span aria-hidden="true">📍</span> {{ $where }}</p>@endif
                @php($badges = array_filter([
                    'phone' => $member->hasValidPhone() ? ['verify-phone', '手機已認證'] : null,
                    'job' => $approved->has('job') ? ['verify-email', '職業 Email 已認證'] : null,
                    'id' => $approved->has('id') ? ['verify-organization', '身分文件已認證'] : null,
                    'church' => $approved->has('church') ? ['verify-church', '教會週報已認證'] : null,
                ]))
                @if ($badges)
                    <ul class="profile-badges">
                        @foreach ($badges as [$icon, $label])<li><img src="{{ asset('images/brand/' . $icon . '.svg') }}" alt="{{ $label }}" width="28" height="28"></li>@endforeach
                    </ul>
                @endif
                <p class="meta">
                    最近登入：{{ $member->last_login_at ? \App\Support\Ago::days($member->last_login_at) : '—' }}
                    ・最近更新：{{ $cp?->updated_at ? \App\Support\Ago::days($cp->updated_at) : '—' }}
                </p>
            @endif
        </div>
        <div class="profile-side">
            @if (! $isMe && ! $blocked)
                @php($level = auth()->user()->friendLevelWith($member))
                <p class="relationship">與您關係：<strong>{{ \App\Models\Friendship::LEVELS[$level] }}</strong>
                    @if ($level > 0)・<a href="{{ route('friends.confirm', $member) }}">解除關係…</a>@endif
                    ・<a href="{{ route('reports.create', $member) }}">檢舉…</a>
                </p>
            @endif
            @if (\App\Http\Controllers\NoteController::canNote(auth()->user(), $member))
                <p class="member-actions">
                    <a class="pill-action pill-note" href="{{ route('notes.show', $member) }}">傳紙條<span class="visually-hidden">給 {{ $member->name }}</span></a>
                    <a class="pill-action pill-gift" href="{{ route('gifts.create', $member) }}"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="18" height="18"> 送禮物</a>
                    <a class="pill-action pill-date" href="{{ route('meetups.create', $member) }}">約會<span class="visually-hidden">（邀請 {{ $member->name }}）</span></a>
                </p>
            @endif
        </div>
    </div>
</section>
