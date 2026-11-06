{{-- 個人首頁上面那張白卡（設計稿 Profile 142:22363）：
     上：封面——在「個人相簿」選的插圖 10 選 1（熊叔叔 2026-11-05「更正個人的封面是在這邊指定」「自己上傳拿掉」）
     下：大頭照疊在輪播下緣、暱稱、一句話、居住地／年齡、認證盾牌、最近登入／更新；右邊與您關係、解除、檢舉、傳紙條／送禮物／約會。
     $member、$isMe、$iBlocked --}}
@php
    $cp = $member->profile;
    $blocked = $iBlocked ?? false;
@endphp
<section class="card profile-card" aria-labelledby="member-name">
    @php($preset = $cp?->coverPreset() ?? 1)
    @php([$vSide, $vPos, $vBand] = \App\Models\Profile::COVER_LAYOUT[$preset ?? 1] ?? ['left', 8, '#f6f0e7'])
    <div class="profile-cover" style="--band: {{ $vBand }}; --vx: {{ $vPos }}%;">
        {{-- 手機用沒有經文字的版本，經文改在下面用網頁文字（熊叔叔 11/6「手機版封面圖字會太小」）；點圖放大的是有經文的原圖 --}}
        <picture>
            <source media="(max-width: 640px)" srcset="{{ asset('images/covers/cover-' . $preset . '-art.svg') }}">
            <img data-zoom src="{{ asset('images/covers/cover-' . $preset . '.svg') }}" alt="封面插圖（經文：{{ \App\Models\Profile::COVER_PRESETS[$preset] }}）" width="551" height="165">
        </picture>
        {{-- 手機版：經文用網頁文字疊在插圖空白處（熊叔叔 11/6 選 C），封面下面補同色的底給大頭照 --}}
        <p class="cover-verse v-{{ $vSide }}">{{ \App\Models\Profile::COVER_VERSES[$preset] }}<span class="cover-verse-ref">{{ \App\Models\Profile::COVER_PRESETS[$preset] }}</span></p>
    </div>

    <div class="profile-row">
        <div class="profile-avatar">@include('partials.avatar', ['user' => $member, 'size' => 'xl'])</div>
        <div class="profile-main">
            @php($approved = $blocked ? collect() : $member->approvedVerifications())
            <h1 id="member-name">{{ $member->name }}@if ($approved->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="20" height="20" class="verified-badge">@endif @if($isMe) <span class="tag">我</span>@endif</h1>
            @if (! $blocked)
                @if (($cp?->headline ?? '') !== '')<p class="headline">{{ $cp->headline }}</p>@endif
                @php($where = collect([$cp?->live_place, $cp?->ageLabel()])->filter()->implode(' / '))
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
                    @if (\App\Http\Controllers\NoteController::canSendNote(auth()->user(), $member))
                        <a class="pill-action pill-note" href="{{ route('notes.show', $member) }}" data-dialog="note">傳紙條<span class="visually-hidden">給 {{ $member->name }}</span></a>
                    @endif
                    @if ($dialogs['sentToday'] ?? false) {{-- 熊叔叔 11/6 選甲：一直出現，今天送過就灰色「今天已送」 --}}
                        <span class="pill-action pill-gift is-done" aria-disabled="true"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="18" height="18"> 今天已送<span class="visually-hidden">禮物給 {{ $member->name }}，明天可以再送</span></span>
                    @else
                        <a class="pill-action pill-gift" href="{{ route('gifts.create', $member) }}" data-dialog="gift"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="18" height="18"> 送禮物</a>
                    @endif
                    @if (auth()->user()->friendLevelWith($member) >= 2) {{-- 約會：聊天好友以上才出現（熊叔叔 11/6「都要按照條件顯示」） --}}
                        <a class="pill-action pill-date" href="{{ route('meetups.create', $member) }}" data-dialog="date">約會<span class="visually-hidden">（邀請 {{ $member->name }}）</span></a>
                    @endif
                </p>
            @endif
        </div>
    </div>
</section>
