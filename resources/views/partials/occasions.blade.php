{{-- 特殊節日（設計稿 PageIndex OccasionBlock；Figma「你的好友 Sherry Wang 生日快到了。送份禮物給她吧！」）。
     $occasions 由 HomeController 給（App\Support\Occasions） --}}
@if (! empty($occasions))
    <section aria-labelledby="occasion-title" class="card occasions">
        <h2 id="occasion-title">特殊節日</h2>
        <ul class="occasion-list">
            @foreach ($occasions as $o)
                <li class="occasion-item occasion-{{ $o['type'] }}">
                    @if ($o['type'] === 'birthday')
                        <img src="{{ asset('images/brand/cake.svg') }}" alt="" width="25" height="25">
                        <p>你的好友 <a href="{{ route('members.show', $o['friend']) }}">{{ $o['friend']->name }}</a>
                            生日快到了！
                            <a href="{{ route('gifts.create', $o['friend']) }}">送份禮物<span class="visually-hidden">給 {{ $o['friend']->name }}</span></a></p>
                    @else
                        <img src="{{ asset('images/brand/fireworks.svg') }}" alt="" width="25" height="25">
                        <p>慶祝與 <a href="{{ route('members.show', $o['friend']) }}">{{ $o['friend']->name }}</a>
                            好友紀念{{ $o['years'] }}週年！</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
