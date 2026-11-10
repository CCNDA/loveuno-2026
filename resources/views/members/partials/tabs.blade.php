{{-- 會員檔案的四個分頁（設計稿 PagePersonal PersonalTabNav：個人資料／自我介紹／心情日記／個人相簿）。$tab、$member、$p、$diaries
     2026-11-11 熊叔叔選「個人資料＋自我介紹合一頁，相簿日記仍分頁」：照設計稿 Profile 142:22363，「個人資料」頁上下兩張卡；
     「自我介紹」分頁鈕跳到同一頁的自我介紹卡，舊網址 ?tab=intro 也顯示同一頁 --}}
@php($tab = $tab === 'intro' ? 'info' : $tab)
<nav class="center-subtabs member-tabs" aria-label="{{ $member->name }} 的檔案">
    <ul>
        @foreach (['info' => '個人資料', 'intro' => '自我介紹', 'album' => '個人相簿', 'diary' => '心情日記'] as $key => $label)
            @php($href = match ($key) { 'info' => route('members.show', $member), 'intro' => route('members.show', $member) . '#member-intro', default => route('members.show', ['user' => $member, 'tab' => $key]) })
            <li><a href="{{ $href }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>

@if ($tab === 'info')
{{-- 設計稿 Profile：「個人資料」「自我介紹」兩張白卡上下排 --}}
<div class="card profile-section">
    <div class="profile-section-head"><h2 class="profile-section-title">個人資料</h2>@if ($isMe ?? false)<a class="section-edit" href="{{ route('profile.edit') }}"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11.3 2.3a1.4 1.4 0 0 1 2 2L5.5 12.1 2.5 13l.9-3 7.9-7.7z" fill="currentColor"/></svg><span class="visually-hidden">編輯個人資料</span></a>@endif</div>
    @php($rows = $p->detailRows())
    @if (empty($rows))
        <p>這位會員還沒有填個人資料。</p>
    @else
        <dl class="profile">
            @foreach ($rows as $dt => $dd)
                <div><dt>{{ $dt }}</dt><dd>{{ $dd }}</dd></div>
            @endforeach
        </dl>
    @endif
</div>

<div class="card profile-section" id="member-intro">
    <div class="profile-section-head"><h2 class="profile-section-title">自我介紹</h2>@if ($isMe ?? false)<a class="section-edit" href="{{ route('profile.intro') }}"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11.3 2.3a1.4 1.4 0 0 1 2 2L5.5 12.1 2.5 13l.9-3 7.9-7.7z" fill="currentColor"/></svg><span class="visually-hidden">編輯自我介紹</span></a>@endif</div>
    {{-- 設計稿 Profile 自我介紹卡：左欄標題、右欄內容一條清單；興趣等用「｜」分隔的文字（不再是膠囊＋小標題，熊叔叔 11/10「排版不舒服」） --}}
    @php($vt = $p->verse_book !== '' ? ($p->verse_text !== '' ? $p->verse_text : (\App\Support\Bible::verse((string) $p->verse_book, (int) $p->verse_chapter, (int) $p->verse_verse) ?? '')) : '')
    @if ($p->intro || $p->hope || $p->verse_book !== '' || ! empty($p->hobbies) || ! empty($p->sports) || ! empty($p->languages))
        <dl class="profile-list">
            {{-- is-long：標題超過四個字的，手機上改成標題在上、內容在下（小鹿 11/11） --}}
            @if ($p->intro)<div><dt>簡介</dt><dd class="prose">{!! nl2br(e($p->intro)) !!}</dd></div>@endif
            @foreach ([['languages', '慣用語言'], ['hobbies', '興趣嗜好'], ['sports', '喜愛的運動']] as [$key, $label])
                @if (! empty($p->{$key}))<div><dt @class(['is-long' => mb_strlen($label) > 4])>{{ $label }}</dt><dd @class(['is-long' => mb_strlen($label) > 4])>{{ implode('｜', $p->{$key}) }}</dd></div>@endif
            @endforeach
            @if ($p->hope)<div><dt class="is-long">期待認識的人</dt><dd class="prose is-long">{!! nl2br(e($p->hope)) !!}</dd></div>@endif
            @if ($p->verse_book !== '')
                {{-- 舊站搬來的只有書卷章節、沒有經文：當下從經文庫查（熊叔叔 11/6「最愛經句沒有顯示經句內容」） --}}
                {{-- 書卷章節先組成一個字串：Blade 的 @endif@if 黏在一起時第二個 @if 不會被編譯（11/11 整頁 500） --}}
                @php($verseRef = trim($p->verse_book . ' ' . $p->verse_chapter . ($p->verse_verse ? ':' . $p->verse_verse : '')))
                <div><dt class="is-long">最愛的經句</dt><dd class="prose is-long">@if ($vt !== ''){!! nl2br(e($vt)) !!}（{{ $verseRef }}）@else{{ $verseRef }}@endif</dd></div>
            @endif
        </dl>
    @endif
    @php($love = \App\Support\LoveHistory::displayRows($p->love_history))
    @if (! empty($love))
        <h3 class="profile-subtitle" id="love-title">戀愛履歷</h3>
        <dl class="profile-list profile-list-qa" aria-labelledby="love-title">
            @foreach ($love as [$q, $a])
                <div><dt>{{ $q }}</dt><dd>{!! nl2br(e($a)) !!}</dd></div>
            @endforeach
        </dl>
    @endif
    @if (! $p->hasIntroduction() && empty($love))
        <p>這位會員還沒有寫自我介紹。</p>
    @endif
</div>
@elseif ($tab === 'diary')
<div class="card profile-section">
    <h2 class="profile-section-title">心情日記</h2>
    @if ($diaries->isEmpty())
        <p>還沒有日記。</p>
    @else
        <ul class="plain-list">
            @foreach ($diaries as $diary)
                <li>
                    <a href="{{ route('diaries.show', $diary) }}">{{ $diary->title }}</a>
                    <span class="meta">・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y/n/j') }}</time>@if ($diary->is_prayer)・<span class="tag tag-prayer">代禱</span>@endif</span>
                </li>
            @endforeach
        </ul>
        {{ $diaries->links('partials.pagination') }}
    @endif
</div>
@else
<div class="card profile-section">
    <div class="profile-section-head"><h2 class="profile-section-title">個人相簿</h2>@if ($isMe ?? false)<a class="section-edit" href="{{ route('album.edit') }}"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11.3 2.3a1.4 1.4 0 0 1 2 2L5.5 12.1 2.5 13l.9-3 7.9-7.7z" fill="currentColor"/></svg><span class="visually-hidden">編輯個人相簿</span></a>@endif</div>
    @include('members.partials.album')
</div>
@endif
