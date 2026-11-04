@extends('layouts.app')

@section('title', '會員管理')

@section('content')
<h1>會員管理</h1>
<form method="GET" action="{{ route('admin.members') }}" class="form form-wide" role="search">
    <div class="field">
        <label for="q">找會員</label>
        <p id="q-hint" class="hint">會員編號（新站或舊站的）、Email、暱稱、手機都可以，打一部分也行。</p>
        <input id="q" name="q" type="search" maxlength="100" value="{{ $q }}" aria-describedby="q-hint">
    </div>
    <fieldset class="field">
        <legend>只看</legend>
        @foreach (['' => '全部', 'suspended' => "停權中（{$suspendedCount}）", 'unverified' => 'Email 或手機還沒驗證', 'deleted' => '已註銷'] as $v => $label)
            <div class="field-check">
                <input id="filter-{{ $v ?: 'all' }}" name="filter" type="radio" value="{{ $v }}" @checked($filter === $v)>
                <label for="filter-{{ $v ?: 'all' }}">{{ $label }}</label>
            </div>
        @endforeach
    </fieldset>
    <button type="submit" class="button">搜尋</button>
</form>

@if ($users->isEmpty())
    <p>找不到符合的會員。</p>
@else
    <p class="meta">共 {{ $users->total() }} 位，新註冊的在上面。</p>
    <table class="news-table">
        <caption class="visually-hidden">會員列表</caption>
        <thead><tr><th scope="col">編號</th><th scope="col">暱稱</th><th scope="col">Email</th><th scope="col">狀態</th><th scope="col">註冊</th><th scope="col">最後登入</th></tr></thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>#{{ $u->id }}@if ($u->legacy_id)<br><span class="meta">舊站 {{ $u->legacy_id }}</span>@endif</td>
                    <td><a href="{{ route('admin.members.show', $u) }}">{{ $u->name }}</a></td>
                    <td>{{ $u->email }}</td>
                    <td>@include('admin.members.status', ['m' => $u])</td>
                    <td>{{ $u->created_at?->shown()->format('Y/n/j') }}</td>
                    <td>{{ $u->last_login_at?->shown()->format('Y/n/j') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links() }}
@endif
@endsection
