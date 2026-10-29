<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 首頁：沒登入是介紹頁；登入後是日記牆（照 loveuno-web 版型：上面好友推薦、下面日記卡片）
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $me = $request->user();
        if (! $me || ! $me->hasVerifiedEmail()) {
            return view('home');
        }

        $blocked = $me->blockRelatedIds();
        $diaries = Diary::visibleTo($me)
            ->whereNotIn('user_id', $blocked)
            ->with('user.profile')
            ->orderByDesc('published_on')->orderByDesc('id')
            ->limit(10)->get();

        return view('wall', [
            'diaries' => $diaries,
            'suggestions' => $this->suggestions($me, $blocked),
        ]);
    }

    // 好友推薦：照自己在「交友條件」填的（性別、年齡、居住地）挑，沒填就不限；最多 6 位、每次隨機
    private function suggestions(User $me, array $blocked): Collection
    {
        $pref = $me->profile;
        $now = (int) now()->format('Y');

        return User::query()
            ->whereKeyNot($me->id)
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $blocked)
            ->whereHas('profile', function ($p) use ($pref, $now) {
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
            })
            ->with('profile')
            ->inRandomOrder() // 會員多了以後要改（每次排序全部會員）；目前幾十位沒問題
            ->limit(6)
            ->get();
    }
}
