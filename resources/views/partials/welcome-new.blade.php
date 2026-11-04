{{-- 歡迎新朋友（設計稿 PageIndex WelcomeNewFriends）：最近加入的會員＋送禮物。$newcomers 由 HomeController 給 --}}
@if ($newcomers->isNotEmpty())
    <section aria-labelledby="welcome-title" class="card welcome-new">
        <h2 id="welcome-title">歡迎新朋友</h2>
        <ul class="welcome-list">
            @foreach ($newcomers as $n)
                <li class="welcome-item">
                    @include('partials.avatar', ['user' => $n, 'size' => 'sm'])
                    <div class="welcome-main">
                        <p class="member-name"><a href="{{ route('members.show', $n) }}">{{ $n->name }}</a></p>
                        <p class="meta">{{ collect([$n->profile->genderLabel(), $n->profile->live_place, $n->profile->ageGroup()])->filter()->implode(' / ') }}</p>
                    </div>
                    <a class="action-button" href="{{ route('gifts.create', $n) }}"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="18" height="18">送禮物<span class="visually-hidden">給 {{ $n->name }}</span></a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
