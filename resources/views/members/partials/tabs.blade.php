{{-- 會員檔案的四個分頁（設計稿 PagePersonal PersonalTabNav：個人資料／自我介紹／心情日記／個人相簿）。$tab、$member、$p、$diaries --}}
<nav class="center-subtabs member-tabs" aria-label="{{ $member->name }} 的檔案">
    <ul>
        @foreach (['info' => '個人資料', 'intro' => '自我介紹', 'album' => '個人相簿', 'diary' => '心情日記'] as $key => $label)
            <li><a href="{{ route('members.show', $key === 'info' ? $member : ['user' => $member, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>

@if ($tab === 'info')
    <h2 class="visually-hidden">個人資料</h2>
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
@elseif ($tab === 'intro')
    <h2 class="visually-hidden">自我介紹</h2>
    @if ($p->intro)
        <section aria-labelledby="intro-title">
            <h3 id="intro-title">簡介</h3>
            <p class="prose">{!! nl2br(e($p->intro)) !!}</p>
        </section>
    @endif
    @if ($p->verse_book !== '')
        <section aria-labelledby="verse-title">
            <h3 id="verse-title">最愛的經句</h3>
            <blockquote class="verse">
                @if ($p->verse_text !== '')<p>{!! nl2br(e($p->verse_text)) !!}</p>@endif
                <footer>{{ $p->verse_book }} {{ $p->verse_chapter }}@if ($p->verse_verse):{{ $p->verse_verse }}@endif</footer>
            </blockquote>
        </section>
    @endif
    @foreach ([['hobbies', '興趣嗜好'], ['sports', '喜愛的運動'], ['languages', '慣用語言']] as [$key, $label])
        @if (! empty($p->{$key}))
            <section aria-labelledby="{{ $key }}-title">
                <h3 id="{{ $key }}-title">{{ $label }}</h3>
                <ul class="tag-list">@foreach ($p->{$key} as $t)<li class="tag">{{ $t }}</li>@endforeach</ul>
            </section>
        @endif
    @endforeach
    @if ($p->hope)
        <section aria-labelledby="hope-title">
            <h3 id="hope-title">期待認識的人</h3>
            <p class="prose">{!! nl2br(e($p->hope)) !!}</p>
        </section>
    @endif
    @if (! $p->hasIntroduction())
        <p>這位會員還沒有寫自我介紹。</p>
    @endif
@elseif ($tab === 'diary')
    <h2 class="visually-hidden">心情日記</h2>
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
@else
    <h2 class="visually-hidden">個人相簿</h2>
    @include('members.partials.album')
@endif
