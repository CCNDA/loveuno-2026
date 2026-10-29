@extends('layouts.app')

@section('title', '審核大頭照')

@section('content')
<h1>審核大頭照</h1>
<p class="meta" role="status">待審核 {{ $pending->count() }} 張</p>

@if ($pending->isEmpty())
    <p>目前沒有要審核的照片。</p>
@else
    <ul class="review-list">
        @foreach ($pending as $p)
            <li class="review-item">
                <img src="{{ route('photos.show', $p->user) }}?v={{ $p->photo_updated_at?->timestamp }}" alt="{{ $p->user->name }} 上傳的照片" width="160" height="160">
                <div>
                    <p class="member-name"><a href="{{ route('members.show', $p->user) }}">{{ $p->user->name }}</a></p>
                    <p class="meta">上傳時間 {{ $p->photo_updated_at?->format('Y/n/j H:i') }}</p>
                    <form method="POST" action="{{ route('admin.photos.decide', $p) }}" class="review-actions">
                        @csrf
                        <input type="hidden" name="photo" value="{{ $p->photo_path }}">
                        <button type="submit" name="decision" value="approve" class="button">通過</button>
                        <button type="submit" name="decision" value="reject" class="button button-secondary">不通過（刪掉）</button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
