{{-- 會員中心的四個圓形分頁（loveuno-web 版型）。$current：account／profile／points／preferences --}}
{{-- 設計稿會員中心（帳號管理 253:15337、個人資料 69:5636…）：標題在外、下面一張大白卡（main 加 center-main，CSS 畫卡片） --}}
@section('main_class', 'wrap wrap-wide center-main')
<h1>會員中心</h1>
<nav class="center-tabs" aria-label="會員中心">
    <ul>
        @foreach ([
            'account' => ['帳號管理', 'center.account'],
            'profile' => ['個人檔案', 'profile.edit'],
            'points' => ['愛積點', 'center.points'],
            'preferences' => ['交友條件', 'center.preferences'],
        ] as $key => [$label, $route])
            <li><a href="{{ route($route) }}" @if($current === $key) aria-current="page" @endif>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
@if ($current === 'profile')
    {{-- 個人檔案底下的小分頁 --}}
    <nav class="center-subtabs" aria-label="個人檔案">
        <ul>
            <li><a href="{{ route('profile.edit') }}" @if(($sub ?? '') === 'info') aria-current="page" @endif>個人資料</a></li>
            <li><a href="{{ route('profile.intro') }}" @if(($sub ?? '') === 'intro') aria-current="page" @endif>自我介紹</a></li>
            <li><a href="{{ route('profile.love') }}" @if(($sub ?? '') === 'love') aria-current="page" @endif>戀愛履歷</a></li>
            <li><a href="{{ route('album.edit') }}" @if(($sub ?? '') === 'album') aria-current="page" @endif>個人相簿</a></li>
            <li><a href="{{ route('photo.edit') }}" @if(($sub ?? '') === 'photo') aria-current="page" @endif>大頭照</a></li>
            <li><a href="{{ route('verify.index') }}" @if(($sub ?? '') === 'verify') aria-current="page" @endif>身份認證</a></li>
        </ul>
    </nav>
@endif
