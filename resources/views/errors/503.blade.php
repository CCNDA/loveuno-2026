@extends('layouts.app')

{{-- 維護中（php artisan down）：設計稿 406（730:9285）「網站維護中...... Under Construction」 --}}
@section('title', '網站維護中')

@section('content')
<div class="error-hero">
    <img src="{{ asset('images/errors/maintenance.svg') }}" alt="" width="650" height="528">
    <h1>網站維護中……<span lang="en">Under Construction</span></h1>
    <p>我們正在更新網站，請稍後再回來。</p>
</div>
@endsection
