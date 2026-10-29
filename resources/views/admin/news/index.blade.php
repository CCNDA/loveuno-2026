@extends('layouts.app')

@section('title', '站務公告管理')

@section('content')
<h1>站務公告管理</h1>
<p><a class="button" href="{{ route('admin.news.create') }}">新增公告</a></p>
@if ($items->isEmpty())
    <p>還沒有公告。</p>
@else
    <table class="news-table">
        <caption class="visually-hidden">所有公告，新增的在上面</caption>
        <thead><tr><th scope="col">標題</th><th scope="col">狀態</th><th scope="col">動作</th></tr></thead>
        <tbody>
            @foreach ($items as $a)
                <tr>
                    <td><a href="{{ route('news.show', $a) }}">{{ $a->title }}</a></td>
                    <td>@if ($a->isPublished())已發佈 {{ $a->published_at->shown()->format('Y/n/j H:i') }}@elseif ($a->published_at)預定 {{ $a->published_at->shown()->format('Y/n/j H:i') }} 發佈@else草稿@endif</td>
                    <td class="table-actions">
                        <a href="{{ route('admin.news.edit', $a) }}">修改<span class="visually-hidden">「{{ $a->title }}」</span></a>
                        <form method="POST" action="{{ route('admin.news.destroy', $a) }}" onsubmit="return confirm('確定要刪掉這則公告嗎？刪掉就救不回來了。')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-button">刪除<span class="visually-hidden">「{{ $a->title }}」</span></button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
