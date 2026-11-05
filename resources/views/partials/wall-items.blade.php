{{-- 生活日記牆的卡片（一批 10 篇）。首頁和「往下拉接下一批」（HomeController，X-Wall-More）共用。$diaries、$scope、$kind --}}
        @foreach ($diaries as $d)
            <li>
                <article class="wall-card" id="d{{ $d->id }}" aria-labelledby="wall-d{{ $d->id }}">
                    <header class="wall-card-head">
                        @include('partials.avatar', ['user' => $d->user])
                        <div>
                            <p class="wall-author"><a href="{{ route('members.show', $d->user) }}">{{ $d->user->name }}</a>@if ($d->user->approvedVerifications()->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="15" height="15" class="verified-badge">@endif</p>
                            <p class="meta"><time datetime="{{ $d->published_on->toDateString() }}">{{ $d->published_on->format('Y-m-d') }}</time>・<span class="tag {{ $d->is_prayer ? 'tag-prayer' : 'tag-diary' }}">{{ $d->is_prayer ? '代禱事項' : '心情日記' }}</span> @if($d->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif</p>
                        </div>
                    </header>
                    <h3 id="wall-d{{ $d->id }}" class="wall-title"><a href="{{ route('diaries.show', $d) }}">{{ $d->title }}</a></h3>
                    <p class="excerpt">{{ \Illuminate\Support\Str::limit($d->body, 120) }}@if (mb_strlen($d->body) > 120) <a href="{{ route('diaries.show', $d) }}" class="read-more">查看更多<span class="visually-hidden">：{{ $d->title }}</span></a>@endif</p>
                    @if ($d->imageVisibleTo(auth()->user()))
                        <img class="diary-image" src="{{ route('diaries.image', $d) }}?v={{ $d->image_updated_at?->timestamp }}" alt="{{ $d->user->name }}〈{{ $d->title }}〉附的照片" loading="lazy">
                    @endif
                    @include('diaries.partials.interact', ['from' => 'wall', 'wallScope' => $scope, 'wallKind' => $kind])
                    @include('diaries.partials.wall-comments', ['wallScope' => $scope, 'wallKind' => $kind])
                </article>
            </li>
        @endforeach
