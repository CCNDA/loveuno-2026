<?php

namespace App\Http\Controllers;

use App\Models\AlbumPhoto;
use App\Models\Profile;
use App\Models\User;
use App\Support\ProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    // 認識朋友：會員列表＋條件搜尋（上線前檢測表 D-01：舊站 member/search.php 的條件——性別、年齡、居住地、
    // 婚姻、信仰、暱稱、只看有照片、職業／身分／教會認證；手機認證新版列出來的都有，所以沒有這個條件）。
    // 年齡用出生年算（跟 ageGroup() 一樣），只拿來篩，不顯示生日。
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'gender' => ['nullable', 'in:男,女'],
            'place' => ['nullable', 'string', 'max:20'],
            'age_min' => ['nullable', 'integer', 'between:18,99'],
            'age_max' => ['nullable', 'integer', 'between:18,99', 'gte:age_min'],
            'marital' => ['nullable', Rule::in(ProfileOptions::MARITAL)],
            'faith' => ['nullable', Rule::in(ProfileOptions::FAITH)],
            'name' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'array'],
            'verified.*' => ['in:job,id,church'],
        ], ['gender.in' => '性別只能選弟兄或姊妹。', 'age_max.gte' => '年齡的「到」要大於或等於「從」。'],
            ['gender' => '性別', 'place' => '居住地', 'age_min' => '年齡（從）', 'age_max' => '年齡（到）', 'marital' => '婚姻狀況', 'faith' => '信仰', 'name' => '暱稱', 'verified.*' => '認證']);
        $year = (int) now()->format('Y');

        $members = User::query()
            ->whereHas('profile')
            ->whereNotNull('email_verified_at') // 還沒驗證 email、手機的新註冊會員不列出來（10/29「1A」）
            ->phoneVerified() // 手機認證過期的也不列（12 個月）
            ->active() // 帳號設定「啟用帳號」關掉的、註銷的不列
            ->whereNotIn('id', $request->user()->blockRelatedIds()) // 封鎖（任一方）就不列出來
            ->with('profile')
            ->when($filters['gender'] ?? null, fn ($q, $g) => $q->whereHas('profile', fn ($p) => $p->where('gender', $g)))
            ->when($filters['place'] ?? null, fn ($q, $pl) => $q->whereHas('profile', fn ($p) => $p->where('live_place', $pl)))
            ->when($filters['age_min'] ?? null, fn ($q, $a) => $q->whereHas('profile', fn ($p) => $p->where('birth_year', '<=', $year - $a)))
            ->when($filters['age_max'] ?? null, fn ($q, $a) => $q->whereHas('profile', fn ($p) => $p->where('birth_year', '>=', $year - $a)))
            ->when($filters['marital'] ?? null, fn ($q, $m) => $q->whereHas('profile', fn ($p) => $p->where('marital', $m)))
            ->when($filters['faith'] ?? null, fn ($q, $f) => $q->whereHas('profile', fn ($p) => $p->where('faith', $f)))
            ->when($filters['name'] ?? null, fn ($q, $n) => $q->where('name', 'like', '%' . addcslashes($n, '\\%_') . '%'))
            ->when($filters['photo'] ?? false, fn ($q) => $q->whereHas('profile', fn ($p) => $p->where('photo_status', 'approved')))
            ->when($filters['verified'] ?? [], function ($q, $kinds) {
                foreach (array_unique($kinds) as $kind) {
                    $q->whereHas('verifications', fn ($v) => $v->where('kind', $kind)->where('status', 'approved'));
                }
            })
            // 示範會員排前面，舊站匯入的測試帳號（legacy_id 有值）排後面，展示時第一眼看到的是有內容的檔案
            ->orderByRaw('legacy_id IS NOT NULL')->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $places = Profile::where('live_place', '<>', '')
            ->distinct()->orderBy('live_place')->pluck('live_place');

        return view('members.index', [
            'members' => $members,
            'places' => $places,
            'filters' => $filters,
            // 「更多條件」有用到就打開
            'moreOpen' => collect(['age_min', 'age_max', 'marital', 'faith', 'name', 'photo', 'verified'])->contains(fn ($k) => ! empty($filters[$k])),
        ]);
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
