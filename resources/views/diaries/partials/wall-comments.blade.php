{{-- 日記牆卡片上的留言（設計稿 DiaryWall 的 CommentZone；熊叔叔 10/29「2照設計」）。
     $d->comments 由 HomeController 載入（已排除封鎖對象，舊的在前）；這裡只放最新 3 則。 --}}
@php
    $bag = $errors->getBag('comment' . $d->id);
    $recent = $d->comments->slice(-3);
@endphp
<div class="wall-comments">
    <form method="POST" action="{{ route('diaries.comments.store', $d) }}" class="wall-comment-form" novalidate>
        @csrf
        <input type="hidden" name="from" value="wall">
        <label for="comment-body-{{ $d->id }}" class="visually-hidden">留言給〈{{ $d->title }}〉</label>
        <input id="comment-body-{{ $d->id }}" name="body" type="text" maxlength="500" placeholder="請輸入留言" required
               value="{{ $bag->any() ? old('body') : '' }}"
               @if ($bag->has('body')) aria-invalid="true" aria-describedby="comment-error-{{ $d->id }}" @endif>
        <button type="submit" class="icon-button"><img src="{{ asset('images/brand/return.svg') }}" alt="送出留言" width="36" height="36"></button>
    </form>
    @if ($bag->has('body'))<p id="comment-error-{{ $d->id }}" class="field-error" role="alert">{{ $bag->first('body') }}</p>@endif

    @if ($recent->isNotEmpty())
        <ol class="comment-list">
            @foreach ($recent as $c)
                <li class="comment-item">
                    @include('partials.avatar', ['user' => $c->user, 'size' => 'sm'])
                    <div class="comment-main">
                        <p class="comment-head">
                            <a href="{{ route('members.show', $c->user) }}">{{ $c->user->name }}</a>
                            <time class="meta" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->ago() }}</time>
                        </p>
                        <p class="comment-body">{!! nl2br(e($c->body)) !!}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
    @if ($d->comments->count() > 3)
        <p><a href="{{ route('diaries.show', $d) }}#comments-title">看全部 {{ $d->comments->count() }} 則留言<span class="visually-hidden">（〈{{ $d->title }}〉）</span></a></p>
    @endif
</div>
