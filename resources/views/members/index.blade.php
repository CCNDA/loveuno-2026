@extends('layouts.app')

@section('title', '認識朋友')

@section('content')
<h1>認識朋友</h1>

<form method="GET" action="{{ route('members.index') }}" class="filters" role="search" aria-label="篩選會員">
    <div class="field">
        <label for="gender">性別</label>
        <select id="gender" name="gender">
            <option value="">不限</option>
            @foreach (['男' => '弟兄', '女' => '姊妹'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['gender'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="place">居住地</label>
        <select id="place" name="place">
            <option value="">不限</option>
            @foreach ($places as $place)
                <option value="{{ $place }}" @selected(($filters['place'] ?? '') === $place)>{{ $place }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="button">篩選</button>
</form>

<p class="meta" role="status">共 {{ $members->total() }} 位</p>

@if ($members->isEmpty())
    <p>沒有符合條件的會員，換個條件試試看。</p>
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
