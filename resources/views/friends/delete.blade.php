@extends('layouts.app')

@section('title', '解除關係')

@section('content')
<h1>確定要和 {{ $member->name }} 解除關係嗎？</h1>
<ul class="prose">
    <li>你們現在是「{{ \App\Models\Friendship::LEVELS[$level] }}」，解除後會變回「路人甲」。</li>
    <li>對方不會收到通知。以前的紙條都還在，之後還是可以傳紙條。</li>
    <li>你們以前互送的禮物紀錄會一起刪掉；想再成為好友，要重新互送禮物。</li>
</ul>

<form method="POST" action="{{ route('friends.destroy', $member) }}" class="review-actions">
    @csrf
    @method('DELETE')
    <button type="submit" class="button button-secondary">確定解除</button>
    <a class="button" href="{{ route('members.show', $member) }}">不要解除</a>
</form>
@endsection
