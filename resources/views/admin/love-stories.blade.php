@extends('layouts.app')

@section('title', '審核愛的見證')

@section('content')
<h1>審核愛的見證</h1>
<p class="meta">通過就會公開在首頁和「愛的見證」（不用登入就看得到）。公開前請跟男女雙方確認都同意。</p>
<section aria-labelledby="pending-title">
    <h2 id="pending-title">待審核（{{ $pending->count() }}）</h2>
    @forelse ($pending as $s)
        <article class="report-item" aria-labelledby="s-{{ $s->id }}">
            <h3 id="s-{{ $s->id }}"><a href="{{ route('love-stories.show', $s) }}">{{ $s->title }}</a></h3>
            <dl class="report-meta">
                <dt>男方</dt><dd>@if ($s->male)<a href="{{ route('members.show', $s->male) }}">{{ $s->male->name }}</a>（#{{ $s->male->id }}）@else（已註銷）@endif</dd>
                <dt>女方</dt><dd>@if ($s->female)<a href="{{ route('members.show', $s->female) }}">{{ $s->female->name }}</a>（#{{ $s->female->id }}）@else（已註銷）@endif</dd>
                <dt>投稿的人</dt><dd>{{ $s->submitter?->name ?? '（已註銷）' }}</dd>
                <dt>照片</dt><dd>{{ $s->photo_path ? '有' : '沒有' }}</dd>
                <dt>時間</dt><dd>{{ $s->created_at->shown()->format('Y/n/j H:i') }}</dd>
            </dl>
            <form method="POST" action="{{ route('admin.love-stories.decide', $s) }}" class="review-actions">
                @csrf
                <div class="field-check">
                    <input id="married-{{ $s->id }}" name="set_married" type="checkbox" value="1" checked>
                    <label for="married-{{ $s->id }}">通過時，同時把{{ $s->male?->name }}、{{ $s->female?->name }}的婚姻狀況改成「已婚」（就不會出現在認識朋友）</label>
                </div>
                <button type="submit" name="decision" value="approve" class="button">通過（公開）<span class="visually-hidden">「{{ $s->title }}」</span></button>
                <button type="submit" name="decision" value="reject" class="button button-secondary">不通過<span class="visually-hidden">「{{ $s->title }}」</span></button>
            </form>
        </article>
    @empty
        <p>目前沒有要審核的見證。</p>
    @endforelse
</section>
<section aria-labelledby="approved-title">
    <h2 id="approved-title">已公開</h2>
    <ul class="plain-list">
        @forelse ($approved as $s)
            <li>
                <a href="{{ route('love-stories.show', $s) }}">{{ $s->title }}</a>・{{ $s->approved_at->shown()->format('Y/n/j') }}
                <form method="POST" action="{{ route('admin.love-stories.decide', $s) }}" class="inline-form" onsubmit="return confirm('確定要撤下這篇嗎？照片會刪掉。')">
                    @csrf
                    <button type="submit" name="decision" value="reject" class="link-button">撤下<span class="visually-hidden">「{{ $s->title }}」</span></button>
                </form>
            </li>
        @empty
            <li>還沒有公開的見證。</li>
        @endforelse
    </ul>
</section>
@endsection
