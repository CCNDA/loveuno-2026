@extends('layouts.app')

@section('title', '交友條件')

@section('content')
@include('center.tabs', ['current' => 'preferences'])

{{-- 照設計稿「交友條件_M」（Figma 396:8860）的欄位和順序。設計稿每欄標必填，新版沒填就是「不拘」（11/4 決定：存過一次就算設了）。
     「想認識」設計稿沒有：照個人資料的性別配異性（熊叔叔 2026-11-05 TG）。 --}}
@php($opposite = ['男' => '姊妹', '女' => '弟兄'][$profile->gender ?? ''] ?? null)
<h2>交友條件</h2>
<p class="hint">「認識朋友」和首頁的好友推薦，會照這裡的條件列出對象；沒選的就是不拘。
    @if ($opposite)想認識的對象：依您個人資料的性別，列出<strong>{{ $opposite }}</strong>。@else請先到<a href="{{ route('profile.edit') }}">個人資料</a>填性別，才知道要幫您列出弟兄還是姊妹。@endif</p>

<form method="POST" action="{{ route('center.preferences.update') }}" class="form form-grid pref-form" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '交友條件還沒存好'])

    @foreach ([['age', '對象年齡', '歲', 18, 99], ['height', '對象身高（cm）', '', 120, 230], ['weight', '對象體重（kg）', '', 30, 200]] as [$k, $label, $unit, $lo, $hi])
        @if ($k === 'age')
            @include('center.partials.pref-range', compact('k', 'label', 'unit', 'lo', 'hi'))

            <div class="field">
                <label for="pref_marital">婚姻狀態</label>
                <select id="pref_marital" name="pref_marital" aria-describedby="pref_marital-hint">
                    <option value="">不拘</option>
                    @foreach (\App\Support\ProfileOptions::MARITAL as $v)
                        <option value="{{ $v }}" @selected(old('pref_marital', $profile->pref_marital) === $v)>{{ $v }}</option>
                    @endforeach
                </select>
                <p id="pref_marital-hint" class="hint">此欄不會顯示於交友檔案。</p>
            </div>

            <div class="field">
                <label for="pref_faith">對象信仰</label>
                <select id="pref_faith" name="pref_faith">
                    <option value="">不拘</option>
                    @foreach (\App\Support\ProfileOptions::FAITH as $v)
                        <option value="{{ $v }}" @selected(old('pref_faith', $profile->pref_faith) === $v)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="pref_place">居住地區</label>
                <select id="pref_place" name="pref_place">
                    <option value="">不拘</option>
                    @foreach ($places as $pl)
                        <option value="{{ $pl }}" @selected(old('pref_place', $profile->pref_place) === $pl)>{{ $pl }}</option>
                    @endforeach
                </select>
            </div>
        @else
            @include('center.partials.pref-range', compact('k', 'label', 'unit', 'lo', 'hi'))
        @endif
    @endforeach

    <button type="submit" class="button">存檔</button>
</form>
@endsection
