{{-- 個人相簿（設計稿 PagePersonalAlbum，上限 9 張）：上傳就公開（10/29 起不用審核）。$album 由 MemberController 給 --}}
@if ($album->isEmpty())
    <p>相簿還沒有照片。@if ($isMe) <a href="{{ route('album.edit') }}">放照片進相簿</a>@endif</p>
@else
    <ul class="album-grid">
        @foreach ($album as $i => $ph)
            <li class="album-item">
                <a href="{{ route('album.show', $ph) }}" target="_blank" rel="noopener">
                    <img src="{{ route('album.show', $ph) }}" alt="{{ $member->name }} 的相簿第 {{ $i + 1 }} 張（開新視窗看大圖）" loading="lazy">
                </a>
            </li>
        @endforeach
    </ul>
    @if ($isMe)<p><a href="{{ route('album.edit') }}">管理我的相簿</a></p>@endif
@endif
