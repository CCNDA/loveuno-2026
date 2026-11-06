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
    public function __invoke(Request $request): View|RedirectResponse|\Illuminate\Http\Response
    {
        $me = $request->user();
        if (! $me || ! $me->hasVerifiedEmail()) {
            return view('home', [ // 歡迎頁（設計稿 PageWelcome）：最新 4 篇愛的見證
                'stories' => \App\Models\LoveStory::published()->limit(4)->get(),
            ]);
        }
        if (\App\Support\Phone::expireIfDue($me) || $me->phone_verified_at === null) {
            return redirect()->route('phone.notice'); // 電郵驗證好了，接著驗證手機（熊叔叔 10/29）
        }

        $blocked = $me->blockRelatedIds();
        // 動態牆（熊叔叔 2026-11-05）：舊站心情日記、代禱兩個服務放在一起；可以只看朋友、只看某一種
        $scope = $request->query('scope') === 'friends' ? 'friends' : 'all';
        $kind = in_array($request->query('kind'), ['diary', 'prayer'], true) ? $request->query('kind') : 'all';
        $friendIds = array_keys(array_filter($me->friendLevels(), fn ($level) => $level >= 1));
        $diaries = Diary::visibleTo($me)
            ->whereNotIn('user_id', $blocked)
            ->when($scope === 'friends', fn ($q) => $q->whereIn('user_id', [...$friendIds, $me->id]))
            ->when($kind !== 'all', fn ($q) => $q->where('is_prayer', $kind === 'prayer'))
            ->whereHas('user', fn ($q) => $q->active()->orWhere('id', $me->id)) // 暫停的帳號的日記不上牆
            ->with(['user.profile', 'user.verifications' => fn ($q) => $q->where('status', 'approved')])
            ->withInteractions($me)
            // 卡片上的留言（設計稿 DiaryWall）：不含封鎖對象的；畫面只放最新 3 則
            ->with(['comments' => fn ($q) => $q->whereNotIn('user_id', $blocked)->whereHas('user', fn ($u) => $u->active())->with('user.profile')->orderBy('created_at')->orderBy('id')])
            ->orderByDesc('published_on')->orderByDesc('id')
            ->simplePaginate(10)->withQueryString();

        // 往下拉接下一批（js/wall-scroll.js 帶這個標頭）：只回卡片＋下一個「載入更多」，不算好友推薦那些
        if ($request->header('X-Wall-More') === '1') {
            return response()->view('partials.wall-more-response', ['diaries' => $diaries, 'scope' => $scope, 'kind' => $kind]);
        }

        return view('wall', [
            'diaries' => $diaries,
            'scope' => $scope,
            'kind' => $kind,
            'suggestions' => $this->suggestions($me, $blocked),
            'newcomers' => $this->newcomers($me, $blocked),
            'newGifts' => Gift::unopenedCountFor($me),
            // 等我回覆的約會邀請（日期還沒到的）
            'pendingDates' => \App\Models\Meetup::where('recipient_id', $me->id)->where('status', 'pending')->where('meet_on', '>', today())->whereNull('recipient_deleted_at')
                ->whereNotIn('sender_id', $blocked)->count(),
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
            ->phoneVerified()
            ->active()
            ->whereHas('profile', $this->matchesPreferences($me))
            ->with('profile')
            ->orderByDesc('email_verified_at')->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    // 好友推薦、歡迎新朋友共用：照我的「交友條件」篩（跟認識朋友同一套，年齡 5 歲一格，見 Profile::preferenceFilter）
    private function matchesPreferences(User $me): \Closure
    {
        return fn ($p) => $p->matchesPreferences($me->profile);
    }

    private function suggestions(User $me, array $blocked): Collection
    {
        return User::query()
            ->whereKeyNot($me->id)
            ->whereNotNull('email_verified_at')
            ->phoneVerified()
            ->active()
            ->whereNotIn('id', $blocked)
            ->whereHas('profile', $this->matchesPreferences($me))
            ->with('profile')
            ->inRandomOrder() // 會員多了以後要改（每次排序全部會員）；目前幾十位沒問題
            ->limit(6)
            ->get();
    }
}
