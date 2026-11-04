<?php

namespace App\Http\Controllers;

use App\Models\AlbumPhoto;
use App\Models\Profile;
use App\Models\User;
use App\Support\ProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    // 認識朋友：照會員自己在「會員中心 → 交友條件」設的對象列出來，另外只能用暱稱找（熊叔叔 11/4 TG：
    // 「認識朋友那邊應該不提供搜尋，頂多搜尋名字，列表應該是他設定的對象」按「留名字搜尋」；
    // 「如果沒有合適的對象，請他回去更改交友條件」）。0.31.0 的條件搜尋（D-01）拿掉。
    // 年齡放寬到 5 歲一格，見 Profile::preferenceFilter。
    public function index(Request $request): View
    {
        $filters = $request->validate(['name' => ['nullable', 'string', 'max:20']], [], ['name' => '暱稱']);
        $me = $request->user();
        $members = $this->listable($me)
            ->whereKeyNot($me->id) // 自己不列（首頁推薦也不列自己）
            ->with('profile')
            ->whereHas('profile', fn ($p) => $p->matchesPreferences($me->profile))
            ->when($filters['name'] ?? null, fn ($q, $n) => $q->where('name', 'like', '%' . addcslashes($n, '\%_') . '%'))
            // 最近登入的排前面（舊站 member/search.php 也是）；沒登入過的排最後
            ->orderByRaw('last_login_at IS NULL')->orderByDesc('last_login_at')->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('members.index', ['members' => $members, 'filters' => $filters, 'pref' => $me->profile]);
    }

    // 「認識朋友」列得出來的會員：有個人檔案、email 和手機驗證過、沒暫停或註銷、跟我沒有封鎖關係、沒有結婚
    private function listable(User $me)
    {
        return User::query()
            // 已婚（管理員設的，例如見證通過的夫妻）不列：交友是給單身的（熊叔叔 11/2 TG 選「甲 不出現」）；檔案、日記照常看得到
            ->whereHas('profile', fn ($p) => $p->whereNotIn('marital', ProfileOptions::MARITAL_ADMIN))
            ->whereNotNull('email_verified_at') // 還沒驗證 email、手機的新註冊會員不列出來（10/29「1A」）
            ->phoneVerified() // 手機認證過期的也不列（12 個月）
            ->active() // 帳號設定「啟用帳號」關掉的、註銷的不列
            ->whereNotIn('id', $me->blockRelatedIds()); // 封鎖（任一方）就不列出來
    }

    public function show(Request $request, User $user): View
    {
        $viewer = $request->user();
        // 對方封鎖我：當作不存在。我封鎖對方：只顯示「已封鎖」和解除按鈕
        abort_if($user->hasBlocked($viewer), 404);
        abort_if(! $user->is_active && ! $viewer->is($user) && ! $viewer->is_admin, 404); // 暫停的帳號：當作不存在
        // 還沒驗證 email、手機（或手機認證過期）的帳號：會員列表不列，直接打網址也當作不存在（小布 0.22.0 驗收發現）
        abort_if((! $user->hasVerifiedEmail() || ! $user->hasValidPhone()) && ! $viewer->is($user) && ! $viewer->is_admin, 404);
        if ($viewer->hasBlocked($user)) {
            return view('members.show', ['member' => $user, 'diaries' => collect(), 'isMe' => false, 'iBlocked' => true]);
        }
        // 四個分頁：個人資料（預設）／自我介紹／心情日記／個人相簿
        $tab = in_array($request->query('tab'), ['intro', 'diary', 'album'], true) ? $request->query('tab') : 'info';
        $album = $tab === 'album'
            ? AlbumPhoto::where('user_id', $user->id)->where('status', '!=', 'rejected')->orderBy('id')->get()->filter->visibleTo($viewer)->values()
            : collect();
        $diaries = $tab === 'diary'
            ? $user->diaries()->visibleTo($viewer)->orderByDesc('published_on')->orderByDesc('id')->paginate(10)->withQueryString()
            : collect();

        return view('members.show', [
            'member' => $user->load('profile'),
            'tab' => $tab,
            'album' => $album,
            'diaries' => $diaries,
            'isMe' => $viewer->is($user),
            'iBlocked' => false,
        ]);
    }

    public function me(Request $request): RedirectResponse
    {
        return redirect()->route('members.show', $request->user());
    }
}
