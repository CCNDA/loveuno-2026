@extends('layouts.app')

@section('title', '封鎖會員')

@section('content')
<h1>確定要封鎖 {{ $member->name }} 嗎？</h1>
<ul class="prose">
    <li>對方不能再傳紙條給您。您們以前的紙條您還看得到（可以當作檢舉的證據），對方看不到。</li>
    <li>對方看不到您的個人檔案、日記和大頭照，您也看不到對方的。</li>
    <li>對方不會收到通知。之後可以在「會員中心 → 帳號管理」解除封鎖。</li>
</ul>
<p>如果對方有騷擾、詐騙等行為，也請告訴管理員。</p>

<form method="POST" action="{{ route('blocks.store', $member) }}" class="review-actions">
    @csrf
    <button type="submit" class="button button-secondary">確定封鎖</button>
    <a class="button" href="{{ url()->previous() === url()->current() ? route('members.index') : url()->previous() }}">不要封鎖</a>
</form>
@endsection
