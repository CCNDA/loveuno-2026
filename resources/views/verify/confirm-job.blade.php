@extends('layouts.app')

@section('title', '職業 Email 認證')

@section('content')
<h1>職業 Email 認證</h1>
<p>請按下面的按鈕，確認 <strong>{{ $verification->company }}</strong> 的 Email 是您本人的。</p>
<form method="POST" action="{{ route('verify.job.confirm.store', ['verification' => $verification->id, 'token' => $token]) }}">
    @csrf
    <button type="submit" class="button">確認，完成 Email 認證</button>
</form>
@endsection
