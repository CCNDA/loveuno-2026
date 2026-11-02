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
    // 年齡只能選 5 歲一格（跟卡片上的年齡層一樣），不然一歲一歲試就試得出別人的出生年（10/29「3A」不公開生日；兩面審查 11/2）。
    public const AGE_FROM = [18, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95];

    public const AGE_TO = [19, 24, 29, 34, 39, 44, 49, 54, 59, 64, 69, 74, 79, 84, 89, 94, 99];

    public const VERIFY_KINDS = ['job' => '職業認證', 'id' => '身分認證', 'church' => '教會認證'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'gender' => ['nullable', Rule::in(['男', '女'])],
            'place' => ['nullable', 'string', 'max:20'],
            'age_min' => ['nullable', 'integer', Rule::in(self::AGE_FROM)],
            'age_max' => ['nullable', 'integer', Rule::in(self::AGE_TO)],
            'marital' => ['nullable', Rule::in(ProfileOptions::MARITAL)],
            'faith' => ['nullable', Rule::in(ProfileOptions::FAITH)],
            'name' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'array'],
            'verified.*' => [Rule::in(array_keys(self::VERIFY_KINDS))],
        ], ['gender.in' => '性別只能選弟兄或姊妹。', 'age_min.in' => '年齡請從選單裡選。', 'age_max.in' => '年齡請從選單裡選。'],
            ['gender' => '性別', 'place' => '居住地', 'age_min' => '年齡（從）', 'age_max' => '年齡（到）', 'marital' => '婚姻狀況', 'faith' => '信仰', 'name' => '暱稱', 'verified.*' => '認證']);
        // 起訖填反就對調（舊站也是這樣）
        // 例如「從 40 到 29」→「從 25 到 44」：兩個邊界各自換到對面那一格的頭尾
        if (! empty($filters['age_min']) && ! empty($filters['age_max']) && $filters['age_min'] > $filters['age_max']) {
            [$filters['age_min'], $filters['age_max']] = [max(18, intdiv($filters['age_max'], 5) * 5), intdiv($filters['age_min'], 5) * 5 + 4];
        }

        $members = $this->listable($request->user())
            ->with('profile')
            // 個人資料的條件合成一個子查詢
            ->whereHas('profile', function ($p) use ($filters) {
                $p->when($filters['gender'] ?? null, fn ($q, $g) => $q->where('gender', $g))
                    ->when($filters['place'] ?? null, fn ($q, $pl) => $q->where('live_place', $pl))
                    ->ageBetween($filters['age_min'] ?? null, $filters['age_max'] ?? null)
                    ->when($filters['marital'] ?? null, fn ($q, $m) => $q->where('marital', $m))
                    ->when($filters['faith'] ?? null, fn ($q, $f) => $q->where('faith', $f))
                    ->when($filters['photo'] ?? false, fn ($q) => $q->where('photo_status', 'approved'));
            })
            ->when($filters['name'] ?? null, fn ($q, $n) => $q->where('name', 'like', '%' . addcslashes($n, '\%_') . '%'))
            ->when($filters['verified'] ?? [], function ($q, $kinds) {
                foreach (array_unique($kinds) as $kind) {
                    $q->whereHas('verifications', fn ($v) => $v->where('kind', $kind)->where('status', 'approved'));
                }
            })
            // 最近登入的排前面（舊站 member/search.php 也是）；沒登入過的排最後
            ->orderByRaw('last_login_at IS NULL')->orderByDesc('last_login_at')->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // 居住地選單：只列「列得出來的會員」填過的地方（被封鎖、暫停、沒驗證的人填的不放進來）
        $places = Profile::whereIn('user_id', $this->listable($request->user())->select('id'))
            ->where('live_place', '<>', '')->distinct()->orderBy('live_place')->pluck('live_place');

        return view('members.index', [
            'members' => $members,
            'places' => $places,
            'filters' => $filters,
            'ageFrom' => self::AGE_FROM,
            'ageTo' => self::AGE_TO,
            'verifyKinds' => self::VERIFY_KINDS,
        ]);
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
