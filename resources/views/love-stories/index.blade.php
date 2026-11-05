@extends('layouts.app')

@section('title', '愛的見證')
@section('main_class', 'wrap wrap-wide center-main page-card')

@section('content')
<h1>愛的見證</h1>
<p>在愛諾園認識另一半的弟兄姊妹，寫下他們的故事。</p>
<p><a class="button" href="{{ route('love-stories.create') }}">我要投稿</a></p>
@if ($stories->isEmpty())
    <p>還沒有公開的見證。您在愛諾園找到另一半了嗎？歡迎投稿！</p>
@else
    <ul class="story-cards">
        @foreach ($stories as $story)
            @include('love-stories.partials.card')
        @endforeach
    </ul>
    {{ $stories->links('partials.pagination') }}
@endif
@endsection
