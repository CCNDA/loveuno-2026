@extends('layouts.app')

@section('title', '刪掉日記')

@section('content')
<h1>確定要刪掉這篇日記嗎？</h1>
<p>「{{ $diary->title }}」刪掉之後就救不回來了。</p>

<form method="POST" action="{{ route('diaries.destroy', $diary) }}" class="review-actions">
    @csrf
    @method('DELETE')
    <button type="submit" class="button button-secondary">確定刪掉</button>
    <a class="button" href="{{ route('diaries.show', $diary) }}">不要刪，回到日記</a>
</form>
@endsection
