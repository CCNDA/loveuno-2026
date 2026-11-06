<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Meetup;
use App\Models\User;
use App\Notifications\NewActivity;
use App\Support\NotEnoughPoints;
use App\Support\Points;
use App\Support\WebPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 約會（約見面邀請）：照舊站 member/private_date*.php，欄位照設計稿 AskOutDialog。Asana 1220005805400335（J）。
// - 要先是「聊天好友」才能邀請（舊站 relationship = 2；交友三步驟的第三步）
// - 邀請扣 10 點（設計稿愛積點頁「邀請約會見面 -10」；舊站扣 5），婉拒、取消都不退（跟舊站一樣）
// - 被邀請的人接受／婉拒；接受後雙方都可以取消；約會日過後被邀請的人確認「已參加」→ 真實好友，或「沒有參加」
// - 每一步都通知對方（推播、Email 通知），信裡不放內容（跟紙條、禮物一樣）
class MeetupController extends Controller
{
    public const COST_TYPE = 'date';

    public function index(Request $request): View
    {
        $me = $request->user();
        $blocked = $me->blockRelatedIds();
        $q = fn ($col, $other) => Meetup::with($other)->where($col, $me->id)->whereNotIn($col === 'sender_id' ? 'recipient_id' : 'sender_id', $blocked)
            ->orderByDesc('meet_on')->orderByDesc('id')->limit(50)->get();

        return view('meetups.index', ['received' => $q('recipient_id', 'sender'), 'sent' => $q('sender_id', 'recipient')]);
    }

    public function create(Request $request, User $user): View
    {
        $me = $request->user();
        abort_unless(NoteController::canNote($me, $user) && $me->profile()->exists(), 404);

        return view('meetups.create', [
            'member' => $user,
            'level' => $me->friendLevelWith($user),
            'balance' => (int) $me->fresh()->points,
            'cost' => Points::COSTS[self::COST_TYPE][0],
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless(NoteController::canNote($me, $user) && $me->profile()->exists(), 404);
        if ($me->friendLevelWith($user) < 2) {
            throw ValidationException::withMessages(['meetup' => sprintf('要先跟 %s 成為「聊天好友」才能邀請約會。', $user->name)]);
        }
        // 已經有一張還沒結束的邀請（等回覆、準備參加）就不能再送（連按兩次也不會扣兩次點）
        if ($this->openInvite($me, $user)) {
            throw ValidationException::withMessages(['meetup' => sprintf('您給 %s 的約會邀請還沒結束，等對方回覆或約會過了再邀請。', $user->name)]);
        }
        $data = $request->validate([
            'meet_on' => ['required', 'date_format:Y-m-d', 'after:today', 'before:' . today()->addYear()->toDateString()],
            'meet_time' => ['required', 'in:' . implode(',', self::times())],
            'place' => ['required', 'string', 'max:100'],
            'activity' => ['required', 'in:' . implode(',', array_keys(Meetup::ACTIVITIES))],
            'company' => ['required', 'in:' . implode(',', array_keys(Meetup::COMPANY))],
            'message' => ['required', 'string', 'max:1000'], // 舊站、設計稿都不能空白
        ], [
            'meet_on.after' => '約會日期要在今天以後。',
            'meet_on.before' => '約會日期要在一年以內。',
            'meet_on.date_format' => '請選擇日期。',
            'meet_time.in' => '請選擇時間。',
            'activity.in' => '請選擇活動類型。',
            'company.in' => '請選擇邀請對象。',
            'message.max' => '內文長度不可超過 1000 個字。',
        ], ['meet_on' => '日期', 'meet_time' => '時間', 'place' => '地點', 'activity' => '活動類型', 'company' => '邀請對象', 'message' => '想說的話']);

        try {
            $meetup = DB::transaction(function () use ($me, $user, $data) {
                DB::table('users')->where('id', $me->id)->lockForUpdate()->value('id'); // 同時送兩個請求：第二個等第一個做完再檢查
                if ($this->openInvite($me, $user)) {
                    throw ValidationException::withMessages(['meetup' => sprintf('您給 %s 的約會邀請還沒結束，等對方回覆或約會過了再邀請。', $user->name)]);
                }
                $m = new Meetup;
                $m->forceFill(['sender_id' => $me->id, 'recipient_id' => $user->id, 'status' => 'pending'] + $data)->save();
                Points::spend($me, self::COST_TYPE, 'date:' . $m->id, '邀請 ' . $user->name . ' 約會見面'); // 不夠就整筆不存（舊站這裡的檢查失效，會扣成負的）

                return $m;
            });
        } catch (NotEnoughPoints) {
            throw ValidationException::withMessages(['meetup' => sprintf('愛諾積點不夠（邀請約會要 %d 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。', Points::COSTS[self::COST_TYPE][0])]);
        }
        $this->notifyOther($user, $meetup, '一個新的約會邀請');

        return redirect()->route('meetups.show', $meetup)->with('status', sprintf('約會邀請送給 %s 了，扣了 %d 點。', $user->name, Points::COSTS[self::COST_TYPE][0]));
    }

    public function show(Request $request, Meetup $meetup): View
    {
        $me = $request->user();
        $this->authorizeParty($me, $meetup);
        $meetup->load(['sender', 'recipient']);

        return view('meetups.show', ['m' => $meetup, 'me' => $me, 'isRecipient' => $meetup->recipient_id === (int) $me->id]);
    }

    // 被邀請的人：接受或婉拒（都要寫幾句話，跟舊站一樣）
    public function respond(Request $request, Meetup $meetup): RedirectResponse
    {
        $me = $request->user();
        $this->authorizeParty($me, $meetup);
        abort_unless($meetup->recipient_id === (int) $me->id, 403);
        $data = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'reply' => ['nullable', 'string', 'max:500'], // 熊叔叔 11/6：清單上直接按「參加／不參加」，想說的話可以不寫
        ], ['decision.required' => '請按「參加」或「不參加」其中一個按鈕。', 'decision.in' => '請按「參加」或「不參加」其中一個按鈕。'], ['reply' => '想對對方說的話']);
        $status = $data['decision'] === 'accept' ? 'accepted' : 'declined';
        if (! $this->transition($meetup, 'pending', ['status' => $status, 'reply' => (string) ($data['reply'] ?? ''), 'responded_at' => now()])) {
            return $this->changed($meetup);
        }
        $this->notifyOther($meetup->sender, $meetup, '約會邀請的回覆');

        return redirect()->route('meetups.show', $meetup)->with('status', $status === 'accepted' ? '您接受了約會邀請，到時候見！' : '您婉拒了這次的約會邀請。');
    }

    // 接受之後、約會日之前，雙方都可以取消（要寫原因）
    public function cancel(Request $request, Meetup $meetup): RedirectResponse
    {
        $me = $request->user();
        $this->authorizeParty($me, $meetup);
        $data = $request->validate(['closing_note' => ['required', 'string', 'max:500']], [], ['closing_note' => '取消的原因']);
        if (! $this->transition($meetup, 'accepted', ['status' => 'cancelled', 'closing_note' => $data['closing_note'], 'closed_by' => $me->id, 'closed_at' => now()])) {
            return $this->changed($meetup);
        }
        $this->notifyOther($meetup->otherThan($me), $meetup, '約會取消的通知');

        return redirect()->route('meetups.show', $meetup)->with('status', '約會取消了。');
    }

    // 約會日過後，被邀請的人確認有沒有參加（舊站 private_date_yesattend／noattend）；已參加 → 聊天好友升「真實好友」
    public function attend(Request $request, Meetup $meetup): RedirectResponse
    {
        $me = $request->user();
        $this->authorizeParty($me, $meetup);
        abort_unless($meetup->recipient_id === (int) $me->id, 403);
        $data = $request->validate([
            'attended' => ['required', 'in:yes,no'],
            'closing_note' => ['required', 'string', 'max:500'],
        ], ['attended.required' => '請按「已參加」或「沒有參加」其中一個按鈕。', 'attended.in' => '請按「已參加」或「沒有參加」其中一個按鈕。'],
            ['closing_note' => $request->input('attended') === 'yes' ? '約會感言' : '沒有參加的原因']);
        $yes = $data['attended'] === 'yes';
        if (! $this->transition($meetup, 'past', ['status' => $yes ? 'attended' : 'absent', 'closing_note' => $data['closing_note'], 'closed_by' => $me->id, 'closed_at' => now()])) {
            return $this->changed($meetup);
        }
        $msg = '謝謝您的回覆。';
        if ($yes && $meetup->sender && $me->friendLevelWith($meetup->sender) === 2) {
            Friendship::raise($me, $meetup->sender, 3);
            $msg = sprintf('您和 %s 成為「真實好友」了！', $meetup->sender->name);
        }
        $this->notifyOther($meetup->sender, $meetup, '約會的回覆');

        return redirect()->route('meetups.show', $meetup)->with('status', $msg);
    }

    // 00:00～23:30，每 30 分鐘（設計稿 AskOutDialog）
    public static function times(): array
    {
        $t = [];
        for ($i = 0; $i < 48; $i++) {
            $t[] = sprintf('%02d:%02d', intdiv($i, 2), $i % 2 * 30);
        }

        return $t;
    }

    private function authorizeParty(User $me, Meetup $meetup): void
    {
        abort_unless($meetup->involves($me), 404);
        abort_if(in_array($meetup->sender_id === (int) $me->id ? $meetup->recipient_id : $meetup->sender_id, $me->blockRelatedIds(), true), 404);
    }

    // 狀態要是 $from 才改（條件式 UPDATE：兩邊同時按也只會成功一次）。$from 是 state()：
    // pending／accepted 要約會日還沒到，past＝accepted＋約會日到了；日期條件也寫進 UPDATE，過午夜那一刻也不會錯
    private function transition(Meetup $meetup, string $from, array $set): bool
    {
        $q = Meetup::whereKey($meetup->id)->where('status', $from === 'past' ? 'accepted' : $from)
            ->whereDate('meet_on', $from === 'past' ? '<=' : '>', today());
        if ($q->update($set + ['updated_at' => now()]) !== 1) {
            return false;
        }
        $meetup->refresh();

        return true;
    }

    private function changed(Meetup $meetup): RedirectResponse
    {
        return redirect()->route('meetups.show', $meetup)->with('status', '這個約會的狀態已經變了，請看看下面最新的狀態。');
    }

    private function openInvite(User $me, User $user): bool
    {
        return Meetup::where('sender_id', $me->id)->where('recipient_id', $user->id)
            ->whereIn('status', ['pending', 'accepted'])->whereDate('meet_on', '>', today())->exists();
    }

    private function notifyOther(?User $other, Meetup $meetup, string $what): void
    {
        if (! $other) {
            return;
        }
        defer(fn () => WebPush::notify($other));
        if ($other->notify_email) {
            defer(fn () => $other->notify(new NewActivity($what, route('meetups.show', $meetup))));
        }
    }
}
