@extends('layouts.app')

@section('title', '站務公告')
@section('main_class', 'wrap wrap-wide center-main page-card')

@section('content')
{{-- 設計稿 PageInformation：表格「公告標題」「發佈時間」 --}}
<h1>站務公告</h1>
{{-- 設計稿 220:15493：表格上面一張插圖（用第 2 張個人化封面，就是設計稿那張） --}}
<div class="about-hero news-hero"><img src="{{ asset('images/covers/cover-2.svg') }}" alt="" width="551" height="165"></div>
@if ($items->isEmpty())
    <p>目前沒有公告。</p>
@else
    <table class="news-table">
        <caption class="visually-hidden">站務公告，新的在上面</caption>
        <thead><tr><th scope="col">公告標題</th><th scope="col">發佈時間</th></tr></thead>
        <tbody>
            @foreach ($items as $a)
                <tr>
                    <td><span class="news-tag">站務公告</span> <a href="{{ route('news.show', $a) }}">{{ $a->title }}</a></td>
                    <td><time datetime="{{ $a->published_at->toIso8601String() }}">{{ $a->published_at->shown()->format('Y/n/j') }}</time></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $items->links('partials.pagination') }}
@endif
@endsection
