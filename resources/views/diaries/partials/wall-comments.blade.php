{{-- 日記牆卡片上的留言（設計稿 DiaryWall 的 CommentZone；熊叔叔 10/29「2照設計」）。
     $d->comments 由 HomeController 載入（已排除封鎖對象，舊的在前）。熊叔叔 11/6：先顯示最新 2 則，其餘按「更多」在原地展開 --}}
@php
    $bag = $errors->getBag('comment' . $d->id);
    $recent = $d->comments->slice(-2);
    $older = $d->comments->slice(0, max(0, $d->comments->count() - 2));
@endphp
<div class="wall-comments">
    @if ($d->commentableBy(auth()->user()))
    <form method="POST" action="{{ route('diaries.comments.store', $d) }}" class="wall-comment-form" novalidate>
        @csrf
        <input type="hidden" name="from" value="wall">
        @include('diaries.partials.wall-filter-inputs')
        <label for="comment-body-{{ $d->id }}" class="visually-hidden">留言給〈{{ $d->title }}〉</label>
        <input id="comment-body-{{ $d->id }}" name="body" type="text" maxlength="500" placeholder="請輸入留言" required
               value="{{ $bag->any() ? old('body') : '' }}"
               @if ($bag->has('body')) aria-invalid="true" aria-describedby="comment-error-{{ $d->id }}" @endif>
        <button type="submit" class="icon-button"><img src="{{ asset('images/brand/return.svg') }}" alt="送出留言" width="36" height="36"></button>
    </form>
    @if ($bag->has('body'))<p id="comment-error-{{ $d->id }}" class="field-error" role="alert">{{ $bag->first('body') }}</p>@endif
    @else
    <p class="hint">成為 {{ $d->user->name }} 的朋友以後才能留言。</p>
    @endif

    @if ($older->isNotEmpty())
        <details class="comment-more">
            <summary>更多 {{ $older->count() }} 則留言<span class="visually-hidden">（〈{{ $d->title }}〉）</span></summary>
            <ol class="comment-list">
                @foreach ($older as $c)
                    <li class="comment-item">
                        @include('partials.avatar', ['user' => $c->user, 'size' => 'sm'])
                        <div class="comment-main">
                            <div class="comment-bubble">
                                <p class="comment-head"><a href="{{ route('members.show', $c->user) }}">{{ $c->user->name }}</a></p>
                                <p class="comment-body">{!! nl2br(e($c->body)) !!}</p>
                            </div>
                            <time class="comment-time" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->ago() }}</time>
                        </div>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif
    @if ($recent->isNotEmpty())
        <ol class="comment-list">
            @foreach ($recent as $c)
                <li class="comment-item">
                    @include('partials.avatar', ['user' => $c->user, 'size' => 'sm'])
                    <div class="comment-main">
                        <div class="comment-bubble">
                            <p class="comment-head"><a href="{{ route('members.show', $c->user) }}">{{ $c->user->name }}</a></p>
                            <p class="comment-body">{!! nl2br(e($c->body)) !!}</p>
                        </div>
                        <time class="comment-time" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->ago() }}</time>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
