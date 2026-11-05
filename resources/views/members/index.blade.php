@extends('layouts.app')

@section('title', '認識朋友')
@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>認識朋友</h1>

@include('partials.errors', ['title' => '暱稱有問題，先顯示全部'])

{{-- 照交友條件列出（熊叔叔 11/4）：顯示會員自己填的條件；實際年齡以 5 歲一格計（Profile::preferenceFilter），註明一句 --}}
@php($min = $pref?->pref_age_min)
@php($max = $pref?->pref_age_max)
@php($f = \App\Models\Profile::preferenceFilter($pref))
@php($cm = fn ($lo, $hi, $u) => match (true) { $lo && $hi => $lo . '～' . $hi . ' ' . $u, (bool) $lo => $lo . ' ' . $u . '以上', (bool) $hi => $hi . ' ' . $u . '以下', default => null })
@php($prefParts = collect([
    ['男' => '弟兄', '女' => '姊妹'][$f['gender']] ?? null,
    match (true) {
        $min && $max => $min . '～' . $max . ' 歲',
        (bool) $min => $min . ' 歲以上',
        (bool) $max => $max . ' 歲以下',
        default => null,
    },
    $pref?->pref_place ?: null,
    $pref?->pref_marital ?: null,
    $pref?->pref_faith ?: null,
    ($h = $cm($pref?->pref_height_min, $pref?->pref_height_max, '公分')) ? '身高 ' . $h : null,
    ($w = $cm($pref?->pref_weight_min, $pref?->pref_weight_max, '公斤')) ? '體重 ' . $w : null,
])->filter())
@if ($members === null)
    {{-- 還沒設交友條件：先請他設定（熊叔叔 11/4 TG），不列人、不給搜尋 --}}
    <p class="pref-summary">請先設定您的交友條件（年齡、婚姻狀態、信仰、居住地區、身高、體重），這裡就會列出您想認識的對象。</p>
    <p><a class="button" href="{{ route('center.preferences') }}">設定交友條件</a></p>
@else
{{-- 設計稿：一張大白卡裡兩欄會員卡（蜜桃框、大頭照、♂／♀ 居住地／年齡、關係、一句話、看完整資料） --}}
<div class="card friends-panel">
<p class="pref-summary">
    依您的交友條件列出：<strong>{{ $prefParts->isNotEmpty() ? $prefParts->implode('・') : '不限' }}</strong>@if ($min || $max)（年齡以 5 歲一格計）@endif
    <a href="{{ route('center.preferences') }}">修改交友條件</a>
    @if (! $f['gender'])<br>填好<a href="{{ route('profile.edit') }}">個人資料</a>的性別，就會自動幫您列出異性。@endif
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
        {{-- 設計稿 405（378:19827）：「你的條件太嚴苛，找不到對象！」＋前往變更交友條件 --}}
        <div class="error-hero error-nomatch">
            <img src="{{ asset('images/errors/nomatch.svg') }}" alt="" width="757" height="343">
            <h2>你的條件太嚴苛，找不到對象！<span lang="en">Your standards are too high, you can't find a friend!</span></h2>
            <p>目前沒有符合您交友條件的會員，例如放寬年齡或居住地看看。</p>
            <p><a class="button pill-submit" href="{{ route('center.preferences') }}">前往變更交友條件</a></p>
        </div>
    @endif
@else
    <ul class="member-grid">
        @foreach ($members as $m)
            <li class="member-card">
                @include('partials.avatar', ['user' => $m, 'size' => 'xl'])
                <div class="member-card-main">
                    <p class="member-name">{{ $m->name }}</p>
                    @php($g = $m->profile->gender)
                    @php($level = auth()->user()->friendLevelWith($m))
                    <p class="member-where">@if ($g)<span class="gender-icon gender-{{ $g === '男' ? 'm' : 'f' }}" aria-hidden="true">{{ $g === '男' ? '♂' : '♀' }}</span>@endif
                        {{ collect([$m->profile->genderLabel(), $m->profile->live_place, $m->profile->ageGroup()])->filter()->implode(' / ') }}
                        @if ($level > 0)<span class="member-level">{{ \App\Models\Friendship::LEVELS[$level] }}</span>@endif</p>
                    @php($line = ($m->profile->headline ?? '') !== '' ? $m->profile->headline : $m->profile->intro)
                    @if ($line)<p class="excerpt">{{ \Illuminate\Support\Str::limit($line, 40) }}</p>@endif
                    <a class="pill-link" href="{{ route('members.show', $m) }}"><span aria-hidden="true">👤</span> 看完整資料<span class="visually-hidden">：{{ $m->name }}</span></a>
                </div>
            </li>
        @endforeach
    </ul>

    {{ $members->links('partials.pagination') }}
@endif
</div>
@endif
@endsection
