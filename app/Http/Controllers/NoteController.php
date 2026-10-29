<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// 傳紙條（loveuno-web 版型 /pass-notes）：左邊對話清單、右邊對話內容。
// 只能寄給已驗證 email 的會員；不能寄給自己；一則最多 1000 字（版型文字「內文長度不可超過1000個字」）。
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        return view('notes.index', ['threads' => $this->threads($request->user()), 'other' => null, 'notes' => collect()]);
    }

    // 可以傳紙條的對象：不是自己、email 已驗證、有個人檔案（跟「認識朋友」列得出來的人一致）
    public static function canNote(User $me, User $user): bool
    {
        return ! $me->is($user) && $user->hasVerifiedEmail() && $user->profile()->exists() && ! $me->blockedEitherWay($user);
    }

    public function show(Request $request, User $user): View
    {
        $me = $request->user();
        abort_unless(self::canNote($me, $user), 404);

        // 打開對話＝對方寄給我的都算讀過
        Note::where('sender_id', $user->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);

        $notes = Note::between($me, $user)->orderBy('created_at')->orderBy('id')->get();

        return view('notes.index', ['threads' => $this->threads($me), 'other' => $user, 'notes' => $notes]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless(self::canNote($me, $user), 404);

        $data = $request->validate(
            ['body' => ['required', 'string', 'max:1000']],
            ['body.max' => '內文長度不可超過 1000 個字。', 'body.required' => '請輸入內容。'],
            ['body' => '內容'],
        );

        $note = new Note(['body' => trim($data['body'])]);
        $note->sender_id = $me->id;
        $note->recipient_id = $user->id;
        $note->save();

        return redirect()->route('notes.show', $user)->with('status', '紙條送出了。');
    }

    // 對話清單：跟我有往來的每一位，最後一則、未讀幾則，依最後時間排
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

        $blocked = $me->blockRelatedIds();
        $rows = $rows->reject(fn ($r) => in_array((int) $r->other_id, $blocked, true))->values(); // 封鎖的對話不列出來
        $users = User::with('profile')->whereIn('id', $rows->pluck('other_id'))->get()->keyBy('id');
        $last = Note::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'user' => $users[$r->other_id] ?? null,
            'last' => $last[$r->last_id] ?? null,
            'unread' => (int) $r->unread,
        ])->filter(fn ($t) => $t->user && $t->last)->values();
    }
}
