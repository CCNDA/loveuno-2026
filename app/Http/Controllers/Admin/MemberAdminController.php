<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Diary;
use App\Models\User;
use App\Support\NotEnoughPoints;
use App\Support\Points;
use App\Support\ProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// 後台：會員搜尋與管理（上線前檢測表 D-02；舊站 admin/member_search、list_member）。
// 熊叔叔 11/2 TG：有問題的帳號「只做停權」（不能登入、別人看不到、資料留著、可以解除）；垃圾帳號也用停權、原因寫「垃圾帳號」。
// 每個動作都寫進 admin_actions。
class MemberAdminController extends Controller
{
    // 停權原因的快速選項（也可以自己寫）
    public const SUSPEND_REASONS = ['垃圾帳號', '騷擾其他會員', '假冒身分', '廣告或詐騙'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', '');
        $users = User::query()->with('profile');

        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            // 手機：打「0912-345-678」「0912 345 678」也要找得到（資料庫存的是純數字）
            $digits = preg_replace('/\D/', '', $q);
            $users->where(function ($w) use ($q, $like, $digits) {
                if (ctype_digit($q)) {
                    // 純數字：會員編號（新站、舊站）
                    $w->where('id', (int) $q)->orWhere('legacy_id', (int) $q);
                }
                if (strlen($digits) >= 4) {
                    $w->orWhere('phone', 'like', '%' . $digits . '%');
                }
                $w->orWhere('email', 'like', $like)->orWhere('name', 'like', $like);
            });
        }
        if ($filter === 'suspended') {
            $users->whereNotNull('suspended_at');
        } elseif ($filter === 'deleted') {
            $users->whereNotNull('deleted_at');
        } elseif ($filter === 'unverified') {
            $users->whereNull('deleted_at')->where(fn ($w) => $w->whereNull('email_verified_at')->orWhereNull('phone_verified_at'));
        }

        return view('admin.members.index', [
            'users' => $users->orderByDesc('id')->paginate(30)->withQueryString(),
            'q' => $q,
            'filter' => $filter,
            'suspendedCount' => User::whereNotNull('suspended_at')->count(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['profile', 'verifications']);

        return view('admin.members.show', [
            'member' => $user,
            'counts' => [
                '日記' => Diary::where('user_id', $user->id)->count(),
                '傳出的紙條' => DB::table('notes')->where('sender_id', $user->id)->count(),
                '收到的紙條' => DB::table('notes')->where('recipient_id', $user->id)->count(),
                '送出的禮物' => DB::table('gifts')->where('sender_id', $user->id)->count(),
                '好友' => DB::table('friendships')->where('user_id', $user->id)->count(),
                '被檢舉' => DB::table('reports')->where('reported_id', $user->id)->count(),
            ],
            'points' => DB::table('point_transactions')->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get(),
            'actions' => AdminAction::with('admin')->where('user_id', $user->id)->orderByDesc('id')->get(),
            'suspendReasons' => self::SUSPEND_REASONS,
            'maritalOptions' => array_merge(ProfileOptions::MARITAL, ProfileOptions::MARITAL_ADMIN),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $data = $request->validateWithBag('suspend', [
            'reason' => ['required', 'string', 'max:200'],
        ], [], ['reason' => '停權原因']);
        abort_if($user->is_admin, 422, '管理員的帳號不能停權。');
        abort_if($user->deleted_at !== null, 422, '已註銷的帳號不用停權。');

        $reason = trim($data['reason']);
        // 檢查和寫入在同一個交易、先鎖住這一列：連按兩次不會留兩筆紀錄、也不會把「停權前的狀態」記成 false
        $done = DB::transaction(function () use ($request, $user, $reason) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if ($fresh->suspended_at !== null) {
                return false;
            }
            $prev = (bool) $fresh->is_active;
            $user->forceFill([
                'suspended_at' => now(), 'suspended_reason' => $reason,
                'suspend_prev_active' => $prev, 'is_active' => false,
            ])->save();
            AdminAction::record($request->user(), $user, 'suspend', ['reason' => $reason, 'prev_active' => $prev]);

            return true;
        });
        if (! $done) {
            return $this->back($user, '這個帳號已經是停權中了。');
        }

        return $this->back($user, sprintf('%s（#%d）已經停權：不能登入，別人也看不到。', $user->name, $user->id));
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $done = DB::transaction(function () use ($request, $user) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if ($fresh->suspended_at === null) {
                return false;
            }
            $active = $fresh->suspend_prev_active ?? true; // 停權前會員自己有沒有關「啟用帳號」
            AdminAction::record($request->user(), $user, 'unsuspend', ['was_reason' => $fresh->suspended_reason, 'restore_active' => $active]);
            $user->forceFill([
                'suspended_at' => null, 'suspended_reason' => '', 'suspend_prev_active' => null, 'is_active' => $active,
            ])->save();

            return true;
        });
        if (! $done) {
            return $this->back($user, '這個帳號沒有停權。');
        }

        $msg = sprintf('%s（#%d）已經解除停權，可以登入了。', $user->name, $user->id);
        if (! $user->is_active) {
            $msg .= '（停權前會員自己關掉了「啟用帳號」，所以別人還是看不到，要等他自己打開。）';
        }

        return $this->back($user, $msg);
    }

    public function marital(Request $request, User $user): RedirectResponse
    {
        abort_if($user->profile === null, 422, '這位會員還沒有個人檔案。');
        $data = $request->validateWithBag('marital', [
            'marital' => ['required', Rule::in(array_merge(ProfileOptions::MARITAL, ProfileOptions::MARITAL_ADMIN))],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['marital' => '婚姻狀況', 'note' => '說明']);

        $before = (string) $user->profile->marital;
        if ($before === $data['marital']) {
            return $this->back($user, '婚姻狀況沒有變。');
        }
        DB::transaction(function () use ($request, $user, $before, $data) {
            $user->profile->forceFill(['marital' => $data['marital']])->save();
            AdminAction::record($request->user(), $user, 'marital', ['from' => $before, 'to' => $data['marital'], 'note' => trim((string) ($data['note'] ?? ''))]);
        });

        return $this->back($user, sprintf('婚姻狀況改成「%s」了。', $data['marital']));
    }

    public function points(Request $request, User $user): RedirectResponse
    {
        abort_if($user->deleted_at !== null, 422, '已註銷的帳號不能加減點。');
        abort_if($user->is($request->user()), 422, '不能幫自己加減點，請另一位管理員處理。'); // 兩面審查 11/2
        $data = $request->validateWithBag('points', [
            'amount' => ['required', 'integer', 'between:-1000,1000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:2', 'max:50'],
        ], ['amount.not_in' => '點數不能是 0。'], ['amount' => '點數', 'reason' => '原因']);

        $amount = (int) $data['amount'];
        $reason = trim($data['reason']);
        try {
            DB::transaction(function () use ($request, $user, $amount, $reason) {
                Points::adjust($user, $amount, $reason);
                AdminAction::record($request->user(), $user, 'points', ['amount' => $amount, 'reason' => $reason, 'balance' => $user->points]);
            });
        } catch (NotEnoughPoints) {
            return back()->withErrors(['amount' => sprintf('積點不夠扣：現在只有 %d 點。', $user->fresh()->points)], 'points')->withInput();
        }

        return $this->back($user, sprintf('%s %d 點了，現在是 %d 點。', $amount > 0 ? '加' : '扣', abs($amount), $user->points));
    }

    public function rename(Request $request, User $user): RedirectResponse
    {
        abort_if($user->deleted_at !== null, 422, '已註銷的帳號不能改暱稱。');
        $data = $request->validateWithBag('rename', [
            'name' => ['required', 'string', 'max:20'], // 跟註冊頁一樣
        ], [], ['name' => '暱稱']);

        $name = trim($data['name']);
        $before = $user->name;
        if ($before === $name) {
            return $this->back($user, '暱稱沒有變。');
        }
        DB::transaction(function () use ($request, $user, $before, $name) {
            $user->forceFill(['name' => $name])->save();
            AdminAction::record($request->user(), $user, 'rename', ['from' => $before, 'to' => $name]);
        });

        return $this->back($user, sprintf('暱稱從「%s」改成「%s」了。', $before, $name));
    }

    // 管理員權限（熊叔叔 11/2 TG：只有最高管理員可以設定）。
    // 不能動自己（免得把自己鎖在外面）；最後一位管理員、最後一位最高管理員不能取消；已註銷、停權中的不能設成管理員。
    public function role(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless($me->is_super_admin, 403, '只有最高管理員可以設定管理員。');
        $data = $request->validateWithBag('role', ['change' => ['required', Rule::in(array_keys(AdminAction::ROLE_CHANGES))]], [], ['change' => '權限']);
        abort_if($user->is($me), 422, '不能改自己的權限，請另一位最高管理員幫忙。');

        $change = $data['change'];
        if (in_array($change, ['admin_on', 'super_on'], true) && ($user->deleted_at !== null || $user->suspended_at !== null)) {
            return $this->back($user, '已註銷或停權中的帳號不能設成管理員。');
        }

        // 「最後一位」的檢查和寫入放在同一個交易，先鎖住全部管理員：兩位最高管理員同時互相取消，第二個會被擋下
        $msg = DB::transaction(function () use ($me, $user, $change) {
            $admins = User::where('is_admin', true)->lockForUpdate()->get(['id', 'is_admin', 'is_super_admin']);
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if ($change === 'admin_off' && $fresh->is_admin && $admins->count() <= 1) {
                return '這是最後一位管理員，不能取消。';
            }
            if (in_array($change, ['admin_off', 'super_off'], true) && $fresh->is_super_admin && $admins->where('is_super_admin', true)->count() <= 1) {
                return '這是最後一位最高管理員，不能取消。';
            }
            $before = ['admin' => (bool) $fresh->is_admin, 'super' => (bool) $fresh->is_super_admin];
            $after = match ($change) {
                'admin_on' => ['admin' => true, 'super' => $before['super']],
                'admin_off' => ['admin' => false, 'super' => false], // 不是管理員就不會是最高管理員
                'super_on' => ['admin' => true, 'super' => true],
                'super_off' => ['admin' => $before['admin'], 'super' => false],
            };
            if ($before === $after) {
                return '權限沒有變。';
            }
            $user->forceFill(['is_admin' => $after['admin'], 'is_super_admin' => $after['super']])->save();
            AdminAction::record($me, $user, 'role', ['change' => $change, 'from' => $before, 'to' => $after]);

            return sprintf('%s（#%d）現在%s。', $user->name, $user->id, $user->roleLabel());
        });

        return $this->back($user, $msg);
    }

    private function back(User $user, string $status): RedirectResponse
    {
        return redirect()->route('admin.members.show', $user)->with('status', $status);
    }
}
