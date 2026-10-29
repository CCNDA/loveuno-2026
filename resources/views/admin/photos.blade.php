@extends('layouts.app')

@section('title', '審核照片')

@section('content')
<h1>審核照片</h1>
@if ($errors->any())
    <div class="error-summary" role="alert" aria-labelledby="error-title">
        <h2 id="error-title">這次沒有審核成功</h2>
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
@foreach (\App\Http\Controllers\PhotoController::KINDS as $kind => $cfg)
    <section aria-labelledby="review-{{ $kind }}">
        <h2 id="review-{{ $kind }}">{{ $cfg['label'] }}</h2>
        <p class="meta">待審核 {{ $pending[$kind]->count() }} 張</p>
        @if ($pending[$kind]->isEmpty())
            <p>目前沒有要審核的{{ $cfg['label'] }}。</p>
        @else
            <ul class="review-list">
                @foreach ($pending[$kind] as $p)
                    <li class="review-item">
                        <img src="{{ route($kind === 'photo' ? 'photos.show' : 'covers.show', $p->user) }}?v={{ $p->{$kind . '_updated_at'}?->timestamp }}"
                             alt="{{ $p->user->name }} 上傳的{{ $cfg['label'] }}" class="review-{{ $kind }}">
                        <div>
                            <p class="member-name"><a href="{{ route('members.show', $p->user) }}">{{ $p->user->name }}</a></p>
                            <p class="meta">上傳時間 {{ $p->{$kind . '_updated_at'}?->format('Y/n/j H:i') }}</p>
                            <form method="POST" action="{{ route('admin.photos.decide', $p) }}" class="review-actions">
                                @csrf
                                <input type="hidden" name="kind" value="{{ $kind }}">
                                <input type="hidden" name="photo" value="{{ $p->{$kind . '_path'} }}">
                                <button type="submit" name="decision" value="approve" class="button">通過</button>
                                <button type="submit" name="decision" value="reject" class="button button-secondary">不通過（刪掉）</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach
@endsection
