<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Notifications\NewActivity;
use App\Support\WebPush;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// 傳紙條（熊叔叔 11/6 照設計 402:13701 改「情書」樣式）：左邊一封一封的清單（可勾選刪除），右邊跟對方往來的信。
// 只能寄給已驗證 email 的會員；不能寄給自己；一則最多 1000 字（版型文字「內文長度不可超過1000個字」）。
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        return view('notes.index', ['letters' => $this->letters($request->user()), 'other' => null, 'notes' => collect()]);
    }

    // 可以傳紙條的對象：不是自己、email 已驗證、有個人檔案（跟「認識朋友」列得出來的人一致）
    public static function canNote(User $me, User $user): bool
    {
        // 雙方都要是啟用中的（暫停的人也不能主動傳，不然對方回不了）
        return ! $me->is($user) && $user->hasVerifiedEmail() && $user->hasValidPhone() && $user->is_active && $me->is_active
            && $user->profile()->exists() && ! $me->blockedEitherWay($user);
    }

    // 可以寄紙條：上面的條件，再加上已經互送禮物成為「普通好友」（熊叔叔 11/6 TG「傳紙條需要相互完成送禮物才能開啟」，交友三步驟第二步）
    public static function canSendNote(User $me, User $user): bool
    {
        return self::canNote($me, $user) && $me->friendLevelWith($user) >= 1;
    }

    public function show(Request $request, User $user): View
    {
        $me = $request->user();
        // 我封鎖對方、或對方暫停／註銷了：還能唯讀看以前的紙條（被騷擾時要留證據），不能再寄
        $readOnly = ! $me->is($user) && ($me->hasBlocked($user) || (! $user->is_active && Note::between($me, $user)->exists()));
        abort_unless($readOnly || self::canNote($me, $user), 404);

        // 打開對話＝對方寄給我的都算讀過
        Note::where('sender_id', $user->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);

        $notes = Note::between($me, $user)->visibleTo($me)->orderBy('created_at')->orderBy('id')->get();

        $needGifts = ! $readOnly && ! self::canSendNote($me, $user);

        return view('notes.index', ['letters' => $this->letters($me), 'other' => $user, 'notes' => $notes, 'readOnly' => $readOnly, 'needGifts' => $needGifts]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless(self::canNote($me, $user), 404);
        if (! self::canSendNote($me, $user)) {
            return redirect()->route('notes.show', $user)->with('status', '要先互送禮物成為「普通好友」，才能傳紙條。');
        }

        $data = $request->validate(
            ['title' => ['nullable', 'string', 'max:20'], 'body' => ['required', 'string', 'max:1000']],
            ['body.max' => '內文長度不可超過 1000 個字。', 'body.required' => '請輸入內容。', 'title.max' => '標題最多 20 個字。'],
            ['body' => '內容', 'title' => '標題'],
        );

        $title = trim((string) ($data['title'] ?? ''));
        $note = new Note(['body' => trim($data['body']), 'title' => $title !== '' ? $title : null]);
        $note->sender_id = $me->id;
        $note->recipient_id = $user->id;
        $note->save();
        Friendship::raiseByNotes($me, $user); // 互傳紙條夠多：普通好友 → 聊天好友
        defer(fn () => WebPush::notify($user)); // 有開推播的裝置
        if ($user->notify_email) { // 帳號設定「Email 通知」；回應送出後才寄，不拖慢送出
            defer(fn () => $user->notify(new NewActivity('一張新紙條', route('notes.index'))));
        }

        return redirect()->route('notes.show', $user)->with('status', '紙條送出了。');
    }

    // 勾選刪除（熊叔叔 11/6 照設計）：只把「我這邊」標成刪掉，對方那邊還在；只能刪自己寄的或寄給自己的
    public function destroy(Request $request): RedirectResponse
    {
        $me = $request->user();
        $ids = collect((array) $request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->all();
        if (! $ids) {
            return redirect()->route('notes.index')->with('status', '請先勾選要刪除的信。');
        }
        $n = Note::whereIn('id', $ids)->where('sender_id', $me->id)->whereNull('sender_deleted_at')->update(['sender_deleted_at' => now()])
            + Note::whereIn('id', $ids)->where('recipient_id', $me->id)->whereNull('recipient_deleted_at')->update(['recipient_deleted_at' => now()]);

        return redirect()->route('notes.index')->with('status', "刪掉了 {$n} 封信。");
    }

    // 信件清單（情書樣式）：我看得到的每一封，新的在前；封鎖我的人寄的不列
    private function letters(User $me)
    {
        $blockedBy = $me->blockedByIds();
        $notes = Note::visibleTo($me)
            ->where(fn ($w) => $w->where('sender_id', $me->id)->orWhereNotIn('sender_id', $blockedBy ?: [0]))
            ->where(fn ($w) => $w->where('recipient_id', $me->id)->orWhereNotIn('recipient_id', $blockedBy ?: [0]))
            ->orderByDesc('id')->limit(100)->get();
        $users = User::with('profile')->whereIn('id', $notes->map(fn ($n) => $n->sender_id === $me->id ? $n->recipient_id : $n->sender_id)->unique())->get()->keyBy('id');

        return $notes->map(function (Note $n) use ($me, $users) {
            $mine = $n->sender_id === $me->id;
            $other = $users[$mine ? $n->recipient_id : $n->sender_id] ?? null;

            return (object) ['note' => $n, 'other' => $other, 'mine' => $mine, 'unread' => ! $mine && $n->read_at === null,
                'blocked' => $other && $me->hasBlocked($other)];
        })->filter(fn ($l) => $l->other)->values();
    }

    // 舊的對話清單（每位一列）：情書樣式改用 letters()，這支留給還在用的地方
    private function threads(User $me)
    {
        $rows = DB::table('notes')
            ->selectRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS other_id', [$me->id])
            ->selectRaw('MAX(id) AS last_id')
            ->selectRaw('SUM(CASE WHEN recipient_id = ? AND read_at IS NULL THEN 1 ELSE 0 END) AS unread', [$me->id])
            ->where(fn ($w) => $w->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))
            ->groupBy('other_id')
            ->orderByDesc('last_id')
            ->limit(100)
            ->get();

        // 封鎖我的人：對話不列出來。我封鎖的人：還列著（可以唯讀看），標「已封鎖」
        $blockedBy = $me->blockedByIds();
        $rows = $rows->reject(fn ($r) => in_array((int) $r->other_id, $blockedBy, true))->values();
        $users = User::with('profile')->whereIn('id', $rows->pluck('other_id'))->get()->keyBy('id');
        $last = Note::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'user' => $users[$r->other_id] ?? null,
            'last' => $last[$r->last_id] ?? null,
            'unread' => (int) $r->unread,
            'blocked' => isset($users[$r->other_id]) && $me->hasBlocked($users[$r->other_id]),
        ])->filter(fn ($t) => $t->user && $t->last)->values();
    }
}
