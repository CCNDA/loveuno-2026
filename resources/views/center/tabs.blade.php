{{-- 會員中心的四個圓形分頁（loveuno-web 版型）。$current：account／profile／points／preferences --}}
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
            <li><a href="{{ route('profile.edit') }}" @if(($sub ?? '') === 'info') aria-current="page" @endif>個人資料與自我介紹</a></li>
            <li><a href="{{ route('photo.edit') }}" @if(($sub ?? '') === 'photo') aria-current="page" @endif>大頭照</a></li>
            <li><span class="subtab-soon">身份認證（年底開放前）</span></li>
        </ul>
    </nav>
@endif
