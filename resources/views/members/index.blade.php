@extends('layouts.app')

@section('title', '認識朋友')

@section('content')
<h1>認識朋友</h1>

@include('partials.errors', ['title' => '暱稱有問題，先顯示全部'])

{{-- 照交友條件列出（熊叔叔 11/4）：顯示會員自己填的條件；實際年齡以 5 歲一格計（Profile::preferenceFilter），註明一句 --}}
@php($min = $pref?->pref_age_min)
@php($max = $pref?->pref_age_max)
@php($prefParts = collect([
    ['男' => '弟兄', '女' => '姊妹'][$pref?->pref_gender] ?? null,
    match (true) {
        $min && $max => $min . '～' . $max . ' 歲',
        (bool) $min => $min . ' 歲以上',
        (bool) $max => $max . ' 歲以下',
        default => null,
    },
    $pref?->pref_place ?: null,
])->filter())
@if ($members === null)
    {{-- 還沒設交友條件：先請他設定（熊叔叔 11/4 TG），不列人、不給搜尋 --}}
    <p class="pref-summary">請先設定您的交友條件（想認識弟兄或姊妹、年齡、居住地），這裡就會列出您想認識的對象。</p>
    <p><a class="button" href="{{ route('center.preferences') }}">設定交友條件</a></p>
@else
<p class="pref-summary">
    依您的交友條件列出：<strong>{{ $prefParts->isNotEmpty() ? $prefParts->implode('・') : '不限' }}</strong>@if ($min || $max)（年齡以 5 歲一格計）@endif
    <a href="{{ route('center.preferences') }}">修改交友條件</a>
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
        <p>符合您交友條件的會員裡，沒有暱稱有「{{ $filters['name'] }}」的。如果要找的人不在您的交友條件裡，可以<a href="{{ route('center.preferences') }}">回去修改交友條件</a>。</p>
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
@endif
@endsection
