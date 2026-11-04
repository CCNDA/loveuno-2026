@extends('layouts.app')

@section('title', '確認送禮物')

@section('content')
{{-- 送出前再確認一次（會員反映誤觸就送出、沒寫到簡訊；熊叔叔 11/2 選 gift_both，Asana 1220127768201283） --}}
<h1>確認送禮物給 {{ $member->name }}</h1>
<p class="prose">請再看一次，確定了再按「確定送出」。送出後不能收回。</p>

<img src="{{ \App\Models\Gift::imageUrl($item) }}" alt="" width="96" height="96">
<dl class="report-meta">
    <dt>禮物</dt>
    <dd>{{ $itemName }}</dd>
    <dt>簡訊</dt>
    <dd>@if ($message === '')<strong>沒有寫</strong>（想寫的話請按「回去修改」）@else{{ $message }}@endif</dd>
    <dt>愛諾積點</dt>
    <dd>會用 {{ $cost }} 點（送出後剩 {{ $balance - $cost }} 點）</dd>
</dl>

<form method="POST" action="{{ route('gifts.store', $member) }}" class="review-actions">
    @csrf
    <input type="hidden" name="item" value="{{ $item }}">
    <input type="hidden" name="message" value="{{ $message }}">
    <button type="submit" name="step" value="send" class="button">確定送出</button>
    <button type="submit" name="step" value="edit" class="button button-light">回去修改</button>
</form>
<p><a href="{{ route('members.show', $member) }}">← 不送了，回 {{ $member->name }} 的檔案</a></p>
@endsection
