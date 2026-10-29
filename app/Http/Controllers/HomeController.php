<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\Gift;
use App\Models\User;
use App\Support\Occasions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 首頁：沒登入是介紹頁；登入後是日記牆（照 loveuno-web 版型：上面好友推薦、下面日記卡片）
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $me = $request->user();
        if (! $me || ! $me->hasVerifiedEmail()) {
            return view('home');
        }
        if ($me->phone_verified_at === null) {
            return redirect()->route('phone.notice'); // 電郵驗證好了，接著驗證手機（熊叔叔 10/29）
        }

        $blocked = $me->blockRelatedIds();
        $diaries = Diary::visibleTo($me)
            ->whereNotIn('user_id', $blocked)
            ->with('user.profile')
            ->withInteractions($me)
            // 卡片上的留言（設計稿 DiaryWall）：不含封鎖對象的；畫面只放最新 3 則
            ->with(['comments' => fn ($q) => $q->whereNotIn('user_id', $blocked)->with('user.profile')->orderBy('created_at')->orderBy('id')])
            ->orderByDesc('published_on')->orderByDesc('id')
            ->limit(10)->get();

        return view('wall', [
            'diaries' => $diaries,
            'suggestions' => $this->suggestions($me, $blocked),
            'newcomers' => $this->newcomers($me, $blocked),
            'newGifts' => Gift::unopenedCountFor($me),
            'occasions' => Occasions::for($me),
        ]);
    }

    // 好友推薦：照自己在「交友條件」填的（性別、年齡、居住地）挑，沒填就不限；最多 6 位、每次隨機
    // 歡迎新朋友（設計稿 WelcomeNewFriends）：最近 30 天驗證好 email、符合我的交友條件的會員，最新的 5 位
    private function newcomers(User $me, array $blocked): Collection
    {
        return User::query()
            ->whereKeyNot($me->id)
            ->whereNotIn('id', $blocked)
            ->where('email_verified_at', '>=', now()->subDays(30))
            ->whereNotNull('phone_verified_at')
            ->whereHas('profile', $this->matchesPreferences($me))
            ->with('profile')
            ->orderByDesc('email_verified_at')->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    // 好友推薦、歡迎新朋友共用：照我的「交友條件」篩
    private function matchesPreferences(User $me): \Closure
    {
        $pref = $me->profile;
        $now = (int) now()->format('Y');

        return function ($p) use ($pref, $now) {
            if ($pref?->pref_gender) {
                $p->where('gender', $pref->pref_gender);
            }
            if ($pref?->pref_place) {
                $p->where('live_place', $pref->pref_place);
            }
            if ($pref?->pref_age_min) {
                $p->where('birth_year', '<=', $now - $pref->pref_age_min);
            }
            if ($pref?->pref_age_max) {
                $p->where('birth_year', '>=', $now - $pref->pref_age_max);
            }
        };
    }

    private function suggestions(User $me, array $blocked): Collection
    {
        return User::query()
            ->whereKeyNot($me->id)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('phone_verified_at')
            ->whereNotIn('id', $blocked)
            ->whereHas('profile', $this->matchesPreferences($me))
            ->with('profile')
            ->inRandomOrder() // 會員多了以後要改（每次排序全部會員）；目前幾十位沒問題
            ->limit(6)
            ->get();
    }
}
