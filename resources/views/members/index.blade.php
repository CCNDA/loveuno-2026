@extends('layouts.app')

@section('title', '認識朋友')

@section('content')
<h1>認識朋友</h1>

@include('partials.errors', ['title' => '暱稱有問題，先顯示全部'])

{{-- 照交友條件列出（熊叔叔 11/4）：這裡只說明用了哪些條件、連到交友條件去改；年齡顯示放寬後的 5 歲一格 --}}
@php($prefParts = collect([
    $pref['gender'] ? ['男' => '弟兄', '女' => '姊妹'][$pref['gender']] : null,
    match (true) {
        $pref['age_min'] && $pref['age_max'] => $pref['age_min'] . '～' . $pref['age_max'] . ' 歲',
        (bool) $pref['age_min'] => $pref['age_min'] . ' 歲以上',
        (bool) $pref['age_max'] => $pref['age_max'] . ' 歲以下',
        default => null,
    },
    $pref['place'],
])->filter())
<p class="pref-summary">
    @if ($prefParts->isNotEmpty())
        依您的交友條件列出：<strong>{{ $prefParts->implode('・') }}</strong>
    @else
        您還沒設定交友條件，先列出所有會員。設定之後，這裡就只會列出您想認識的對象。
    @endif
    <a href="{{ route('center.preferences') }}">{{ $prefParts->isNotEmpty() ? '修改交友條件' : '設定交友條件' }}</a>
</p>

<form method="GET" action="{{ route('members.index') }}" class="filters" role="search" aria-label="用暱稱找會員">
    <div class="field">
        <label for="name">用暱稱找</label>
        <input id="name" name="name" type="search" maxlength="20" value="{{ old('name', $filters['name'] ?? '') }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="button">搜尋</button>
    @if (! empty($filters['name']))<a href="{{ route('members.index') }}">清除</a>@endif
</form>

<p class="meta" role="status">共 {{ $members->total() }} 位</p>

@if ($members->isEmpty())
    @if (! empty($filters['name']))
        <p>符合您交友條件的會員裡，沒有暱稱有「{{ $filters['name'] }}」的。</p>
    @else
        <p>目前沒有符合您交友條件的會員。可以<a href="{{ route('center.preferences') }}">回去修改交友條件</a>，例如放寬年齡或居住地。</p>
    @endif
@else
    <ul class="member-grid">
        @foreach ($members as $m)
            <li class="member-card">
                @include('partials.avatar', ['user' => $m])
                <div>
                    <p class="member-name"><a href="{{ route('members.show', $m) }}">{{ $m->name }}</a></p>
                    <p class="meta">
                        {{ collect([$m->profile->genderLabel(), $m->profile->ageGroup(), $m->profile->live_place])->filter()->implode('・') }}
                    </p>
                    @php($level = auth()->user()->friendLevelWith($m))
                    @if ($level > 0)<p class="meta"><span class="tag">{{ \App\Models\Friendship::LEVELS[$level] }}</span></p>@endif
                    @if ($m->profile->intro)
                        <p class="excerpt">{{ \Illuminate\Support\Str::limit($m->profile->intro, 40) }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    {{ $members->links('partials.pagination') }}
@endif
@endsection
