@extends('layouts.app')

@section('title', '約會紀錄')

@section('content')
<h1>約會紀錄</h1>
<p class="meta">只能看，不能改（照舊站 admin/list_date.php）。</p>
@if ($items->isEmpty())
    <p>還沒有約會紀錄。</p>
@else
    <table class="news-table">
        <caption class="visually-hidden">所有約會，新的在上面</caption>
        <thead><tr><th scope="col">邀請的人</th><th scope="col">被邀請的人</th><th scope="col">時間地點</th><th scope="col">狀態</th></tr></thead>
        <tbody>
            @foreach ($items as $m)
                <tr>
                    <td>@if ($m->sender)<a href="{{ route('members.show', $m->sender) }}">{{ $m->sender->name }}</a>@endif</td>
                    <td>@if ($m->recipient)<a href="{{ route('members.show', $m->recipient) }}">{{ $m->recipient->name }}</a>@endif</td>
                    <td>{{ $m->meet_on->format('Y/n/j') }} {{ $m->meet_time }}・{{ $m->place }}（{{ \App\Models\Meetup::ACTIVITIES[$m->activity] ?? '' }}）</td>
                    <td>{{ $m->stateLabel() }}@if ($m->reply || $m->closing_note)<br><span class="meta">{{ \Illuminate\Support\Str::limit($m->closing_note ?? $m->reply, 60) }}</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $items->links('partials.pagination') }}
@endif
@endsection
