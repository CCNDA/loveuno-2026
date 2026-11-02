@extends('layouts.app')

@section('title', '會員管理：' . $member->name)

@section('content')
<h1>{{ $member->name }}（#{{ $member->id }}）</h1>
<p class="meta"><a href="{{ route('admin.members') }}">回會員管理</a>@unless ($member->deleted_at)・<a href="{{ route('members.show', $member) }}">看他的會員檔案</a>@endunless</p>

<section aria-labelledby="basic-title">
    <h2 id="basic-title">帳號</h2>
    <dl class="report-meta">
        <dt>狀態</dt><dd>@include('admin.members.status', ['m' => $member])</dd>
        @if ($member->suspended_at)<dt>停權時間</dt><dd>{{ $member->suspended_at->shown()->format('Y/n/j H:i') }}</dd>@endif
        <dt>Email</dt><dd>{{ $member->email }}@if ($member->email_verified_at)（{{ $member->email_verified_at->shown()->format('Y/n/j') }} 驗證）@endif</dd>
        <dt>手機</dt><dd>{{ $member->phone ?: '—' }}@if ($member->phone_verified_at)（{{ $member->phone_verified_at->shown()->format('Y/n/j') }} 驗證）@endif</dd>
        @if ($member->legacy_id)<dt>舊站會員編號</dt><dd>{{ $member->legacy_id }}</dd>@endif
        <dt>註冊</dt><dd>{{ $member->created_at?->shown()->format('Y/n/j H:i') }}</dd>
        <dt>最後登入</dt><dd>{{ $member->last_login_at?->shown()->format('Y/n/j H:i') ?? '—' }}</dd>
        <dt>愛諾積點</dt><dd>{{ $member->points }} 點</dd>
    </dl>
</section>

@if ($member->profile)
<section aria-labelledby="profile-title">
    <h2 id="profile-title">個人資料</h2>
    <dl class="report-meta">
        @if ($member->profile->real_name !== '')<dt>真實姓名</dt><dd>{{ $member->profile->real_name }}</dd>@endif
        @foreach ($member->profile->detailRows() as $label => $value)
            <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
        @endforeach
        <dt>大頭照</dt><dd>{{ ['approved' => '通過', 'pending' => '待審核', 'rejected' => '沒通過'][$member->profile->photo_status] ?? '沒有' }}</dd>
    </dl>
</section>
@endif

<section aria-labelledby="verify-title">
    <h2 id="verify-title">身份認證</h2>
    @if ($member->verifications->isEmpty())
        <p>沒有申請過。</p>
    @else
        <ul class="plain-list">
            @foreach ($member->verifications as $v)
                <li>{{ $v->label() }}：{{ $v->statusLabel() }}</li>
            @endforeach
        </ul>
    @endif
</section>

<section aria-labelledby="count-title">
    <h2 id="count-title">活動</h2>
    <dl class="report-meta">
        @foreach ($counts as $label => $n)
            <dt>{{ $label }}</dt><dd>{{ $n }}</dd>
        @endforeach
    </dl>
</section>

@unless ($member->deleted_at || $member->is_admin)
<section aria-labelledby="suspend-title">
    <h2 id="suspend-title">停權</h2>
    @if ($member->suspended_at)
        <p>停權中，原因：{{ $member->suspended_reason }}。解除後就可以登入，別人也看得到他（如果停權前他自己沒有暫停帳號）。</p>
        <form method="POST" action="{{ route('admin.members.unsuspend', $member) }}" onsubmit="return confirm('確定要解除這位會員的停權嗎？')">
            @csrf
            <button type="submit" class="button">解除停權</button>
        </form>
    @else
        @php($bag = $errors->getBag('suspend'))
        <p>停權後：不能登入（已經登入的會被登出）、別人看不到他的檔案和日記、不能傳紙條或送禮物給他。資料都留著，之後可以解除。</p>
        <form method="POST" action="{{ route('admin.members.suspend', $member) }}" class="form" novalidate onsubmit="return confirm('確定要停權這位會員嗎？')">
            @csrf
            <div class="field">
                <label for="suspend-reason">原因</label>
                <p id="suspend-reason-hint" class="hint">會留在紀錄裡（會員看不到）。垃圾帳號請寫「垃圾帳號」。</p>
                <input id="suspend-reason" name="reason" type="text" maxlength="200" list="suspend-reasons" value="{{ old('reason') }}" required
                       aria-describedby="suspend-reason-hint{{ $bag->has('reason') ? ' suspend-reason-error' : '' }}" @if ($bag->has('reason')) aria-invalid="true" @endif>
                <datalist id="suspend-reasons">
                    @foreach ($suspendReasons as $r)<option value="{{ $r }}">@endforeach
                </datalist>
                @if ($bag->has('reason'))<p id="suspend-reason-error" class="field-error" role="alert">{{ $bag->first('reason') }}</p>@endif
            </div>
            <button type="submit" class="button button-secondary">停權</button>
        </form>
    @endif
</section>
@endunless

@if ($member->profile && ! $member->deleted_at)
<section aria-labelledby="marital-title">
    <h2 id="marital-title">婚姻狀況</h2>
    @php($bag = $errors->getBag('marital'))
    <p>現在：{{ $member->profile->marital ?: '沒填' }}。「已婚」只有管理員能設（例如愛的見證通過後），會員自己的選單裡沒有。</p>
    <form method="POST" action="{{ route('admin.members.marital', $member) }}" class="form" novalidate>
        @csrf
        <div class="field">
            <label for="marital-select">改成</label>
            <select id="marital-select" name="marital" required @if ($bag->has('marital')) aria-invalid="true" aria-describedby="marital-select-error" @endif>
                @foreach ($maritalOptions as $o)<option value="{{ $o }}" @selected(old('marital', $member->profile->marital) === $o)>{{ $o }}</option>@endforeach
            </select>
            @if ($bag->has('marital'))<p id="marital-select-error" class="field-error" role="alert">{{ $bag->first('marital') }}</p>@endif
        </div>
        <div class="field">
            <label for="marital-note">說明（可以不填）</label>
            <input id="marital-note" name="note" type="text" maxlength="200" value="{{ old('note') }}">
        </div>
        <button type="submit" class="button">改婚姻狀況</button>
    </form>
</section>
@endif

@unless ($member->deleted_at)
<section aria-labelledby="points-title">
    <h2 id="points-title">加減愛諾積點</h2>
    @php($bag = $errors->getBag('points'))
    <form method="POST" action="{{ route('admin.members.points', $member) }}" class="form" novalidate>
        @csrf
        <div class="field">
            <label for="points-amount">點數</label>
            <p id="points-amount-hint" class="hint">加點打正數（例如 10），扣點打負數（例如 -10）；一次最多 1000 點。</p>
            <input id="points-amount" name="amount" type="number" min="-1000" max="1000" step="1" value="{{ old('amount') }}" required
                   aria-describedby="points-amount-hint{{ $bag->has('amount') ? ' points-amount-error' : '' }}" @if ($bag->has('amount')) aria-invalid="true" @endif>
            @if ($bag->has('amount'))<p id="points-amount-error" class="field-error" role="alert">{{ $bag->first('amount') }}</p>@endif
        </div>
        <div class="field">
            <label for="points-reason">原因</label>
            <p id="points-reason-hint" class="hint">會員的積點紀錄會看到「管理員調整：（原因）」。</p>
            <input id="points-reason" name="reason" type="text" maxlength="50" value="{{ old('reason') }}" required
                   aria-describedby="points-reason-hint{{ $bag->has('reason') ? ' points-reason-error' : '' }}" @if ($bag->has('reason')) aria-invalid="true" @endif>
            @if ($bag->has('reason'))<p id="points-reason-error" class="field-error" role="alert">{{ $bag->first('reason') }}</p>@endif
        </div>
        <button type="submit" class="button">送出</button>
    </form>
    @if ($points->isNotEmpty())
        <h3>最近 10 筆積點紀錄</h3>
        <ul class="plain-list">
            @foreach ($points as $p)
                <li>{{ \Illuminate\Support\Carbon::parse($p->created_at)->shown()->format('Y/n/j') }}　{{ $p->label }}　{{ $p->amount > 0 ? '+' : '' }}{{ $p->amount }}</li>
            @endforeach
        </ul>
    @endif
</section>

<section aria-labelledby="rename-title">
    <h2 id="rename-title">改暱稱</h2>
    @php($bag = $errors->getBag('rename'))
    <form method="POST" action="{{ route('admin.members.rename', $member) }}" class="form" novalidate>
        @csrf
        <div class="field">
            <label for="rename-name">新暱稱</label>
            <input id="rename-name" name="name" type="text" maxlength="20" value="{{ old('name', $member->name) }}" required
                   @if ($bag->has('name')) aria-invalid="true" aria-describedby="rename-name-error" @endif>
            @if ($bag->has('name'))<p id="rename-name-error" class="field-error" role="alert">{{ $bag->first('name') }}</p>@endif
        </div>
        <button type="submit" class="button">改暱稱</button>
    </form>
</section>
@endunless

<section aria-labelledby="role-title">
    <h2 id="role-title">管理員權限</h2>
    <p>現在：{{ $member->is_super_admin ? '最高管理員（可以設定別人當管理員）' : ($member->is_admin ? '管理員' : '一般會員') }}。</p>
    @if (! auth()->user()->is_super_admin)
        <p class="meta">只有最高管理員可以改。</p>
    @elseif ($member->is(auth()->user()))
        <p class="meta">不能改自己的權限，請另一位最高管理員幫忙。</p>
    @elseif (! $member->deleted_at)
        <form method="POST" action="{{ route('admin.members.role', $member) }}" class="review-actions" onsubmit="return confirm('確定要改這位會員的管理員權限嗎？')">
            @csrf
            @if (! $member->is_admin)
                @unless ($member->suspended_at)<button type="submit" name="change" value="admin_on" class="button">設為管理員</button>@endunless
            @else
                @if ($member->is_super_admin)
                    <button type="submit" name="change" value="super_off" class="button button-secondary">取消最高管理員</button>
                @else
                    <button type="submit" name="change" value="super_on" class="button">設為最高管理員</button>
                @endif
                <button type="submit" name="change" value="admin_off" class="button button-secondary">取消管理員</button>
            @endif
        </form>
    @endif
</section>

<section aria-labelledby="log-title">
    <h2 id="log-title">管理紀錄</h2>
    @if ($actions->isEmpty())
        <p>還沒有。</p>
    @else
        <ul class="plain-list">
            @foreach ($actions as $a)
                <li>{{ $a->created_at->shown()->format('Y/n/j H:i') }}　{{ $a->admin?->name }}　{{ $a->label() }}
                    @switch($a->action)
                        @case('suspend')：{{ $a->detail['reason'] ?? '' }}@break
                        @case('marital')：{{ ($a->detail['from'] ?? '') ?: '沒填' }} → {{ $a->detail['to'] ?? '' }}@if (! empty($a->detail['note']))（{{ $a->detail['note'] }}）@endif @break
                        @case('points')：{{ ($a->detail['amount'] ?? 0) > 0 ? '+' : '' }}{{ $a->detail['amount'] ?? '' }}（{{ $a->detail['reason'] ?? '' }}）@break
                        @case('rename')：{{ $a->detail['from'] ?? '' }} → {{ $a->detail['to'] ?? '' }}@break
                        @case('role')：{{ \App\Models\AdminAction::ROLE_CHANGES[$a->detail['change'] ?? ''] ?? '' }}@break
                    @endswitch
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
