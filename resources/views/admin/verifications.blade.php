@extends('layouts.app')

@section('title', '審核身份認證')

@section('content')
<h1>審核身份認證</h1>
<p class="meta">待審核 {{ $pending->count() }} 件。照片審完（通過或不通過）就會刪掉。</p>

@if ($pending->isEmpty())
    <p>目前沒有要審核的身份認證。</p>
@else
    <ol class="report-list">
        @foreach ($pending as $v)
            <li class="report-item">
                <article aria-labelledby="v-{{ $v->id }}">
                    <h2 id="v-{{ $v->id }}">{{ $v->label() }}：<a href="{{ route('members.show', $v->user) }}">{{ $v->user->name }}</a></h2>
                    <dl class="report-meta">
                        @if ($v->kind === 'job')
                            <dt>公司(學校)</dt><dd>{{ $v->company }}</dd>
                            <dt>網址</dt><dd>{{ $v->company_url }}</dd>
                            <dt>Email</dt><dd>{{ $v->company_email }}（已點認證信）</dd>
                        @else
                            <dt>會員資料</dt><dd>{{ collect([$v->user->profile?->genderLabel(), $v->user->profile?->ageGroup(), $v->user->profile?->real_name ? '真實姓名 ' . $v->user->profile->real_name : null, $v->kind === 'church' ? '教會 ' . $v->user->profile?->church : null])->filter()->implode('・') }}</dd>
                            <dt>照片</dt><dd><a href="{{ route('admin.verifications.photo', $v) }}" target="_blank" rel="noopener">看照片<span class="visually-hidden">（{{ $v->user->name }}，開新視窗）</span></a></dd>
                        @endif
                        <dt>送出時間</dt><dd>{{ $v->updated_at->shown()->format('Y/n/j H:i') }}</dd>
                    </dl>
                    @php($bag = $errors->getBag('v' . $v->id))
                    <form method="POST" action="{{ route('admin.verifications.decide', $v) }}" class="form" novalidate>
                        @csrf
                        <input type="hidden" name="version" value="{{ $v->updated_at?->getTimestamp() }}">
                        <div class="field">
                            <label for="reason-{{ $v->id }}">不通過的理由（按「不通過」才要選；會員會收到）</label>
                            <select id="reason-{{ $v->id }}" name="reason" @if ($bag->has('reason')) aria-invalid="true" aria-describedby="reason-{{ $v->id }}-error" @endif>
                                <option value="">請選擇</option>
                                @foreach (\App\Models\Verification::REJECT_REASONS[$v->kind] as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                            @if ($bag->has('reason'))<p id="reason-{{ $v->id }}-error" class="field-error" role="alert">{{ $bag->first('reason') }}</p>@endif
                        </div>
                        <div class="review-actions">
                            <button type="submit" name="decision" value="approve" class="button">通過<span class="visually-hidden">（{{ $v->user->name }}）</span></button>
                            <button type="submit" name="decision" value="reject" class="button button-secondary">不通過<span class="visually-hidden">（{{ $v->user->name }}）</span></button>
                        </div>
                    </form>
                </article>
            </li>
        @endforeach
    </ol>
@endif
@endsection
