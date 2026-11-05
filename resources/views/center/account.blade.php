@extends('layouts.app')

@section('title', '帳號管理')

@section('content')
@include('center.tabs', ['current' => 'account'])

@include('center.settings')

<h2 id="blocked-title">封鎖名單</h2>
@if ($blocked->isEmpty())
    <p class="hint">您沒有封鎖任何人。</p>
@else
    <ul class="plain-list" aria-labelledby="blocked-title">
        @foreach ($blocked as $b)
            <li class="blocked-item">
                {{ $b->name }}
                <form method="POST" action="{{ route('blocks.destroy', $b) }}" class="inline-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-button">解除封鎖<span class="visually-hidden">：{{ $b->name }}</span></button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
@endsection
