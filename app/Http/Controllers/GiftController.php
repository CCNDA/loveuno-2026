<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Gift;
use App\Models\User;
use App\Notifications\NewActivity;
use App\Support\NotEnoughPoints;
use App\Support\Points;
use App\Support\WebPush;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 送禮物（設計稿 SendGiftDialog；舊站 member/gift.php）：
// 一次 3 點、同一位會員一天一份、簡訊 20 字內；對方也送過我禮物，就成為「普通好友」（熊叔叔 10/29「1A」）。
class GiftController extends Controller
{
    // 勾選刪除：只把我這邊標成刪掉，對方還看得到（熊叔叔 11/6 #9）
    public function destroy(Request $request): RedirectResponse
    {
        $ids = (array) $request->input('ids', []);
        if (! $ids) {
            return redirect()->route('gifts.index')->with('status', '請先勾選要刪除的禮物。');
        }
        $me = $request->user();
        // 熊叔叔 11/7 TG「收到禮物的處理應該要有一個回禮和刪除，刪除可以退對方點數」（按甲）：
        // 刪除「還沒回禮」的禮物＝婉拒，退送禮的人 3 點並通知他；已經回禮（成為朋友）的只從自己這邊拿掉、不退。
        // Points::award 同一個 type＋ref 只會給一次，再按一次刪除也不會重複退。
        $refunded = 0;
        $mine = Gift::with('sender')->whereIn('id', collect($ids)->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->all())
            ->where('recipient_id', $me->id)->whereNull('recipient_deleted_at')->whereNull('refunded_at')->get();
        foreach ($mine as $g) {
            // 回禮過＝我送過禮物給他，或已經是朋友；這兩種都不退
            $returned = $me->friendLevelWith($g->sender ?? $me) >= 1
                || Gift::where('sender_id', $me->id)->where('recipient_id', $g->sender_id)->exists();
            if (! $g->sender || $returned) {
                continue;
            }
            $amount = Points::COSTS['gift'][0];
            if (Points::award($g->sender, 'gift_refund', 'gift:' . $g->id, $amount, $me->name . ' 沒有收下禮物，退回積點')) {
                $g->forceFill(['refunded_at' => now()])->save();
                $refunded++;
            }
        }
        $n = Gift::hideFor($me, $ids);

        return redirect()->route('gifts.index')->with('status', "刪掉了 {$n} 份禮物。"
            . ($refunded ? sprintf('其中 %d 份還沒回禮，已退 %d 點給送禮的人。', $refunded, Points::COSTS['gift'][0]) : ''));
    }

    public function index(Request $request): View
    {
        $me = $request->user();
        $blocked = $me->blockRelatedIds();
        // 熊叔叔 11/6 決定 #9：左邊清單（收到＋送出，新的在前，可勾選刪除），右邊卡片；?with=會員 只看跟那位的
        $letters = Gift::with(['sender', 'recipient'])->visibleTo($me)
            ->where(fn ($w) => $w->where(fn ($r) => $r->where('recipient_id', $me->id)->whereNotIn('sender_id', $blocked))
                ->orWhere(fn ($s) => $s->where('sender_id', $me->id)->whereNotIn('recipient_id', $blocked)))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get()
            ->filter(fn (Gift $g) => $g->sender && $g->recipient)->values();
        $with = (int) $request->query('with', 0);
        $other = $with ? $letters->map(fn (Gift $g) => $g->sender_id === (int) $me->id ? $g->recipient : $g->sender)
            ->first(fn (User $u) => (int) $u->id === $with) : null;
        $cards = $other ? $letters->filter(fn (Gift $g) => in_array((int) $other->id, [$g->sender_id, $g->recipient_id], true))->values() : $letters;
        $unopened = $letters->where('recipient_id', (int) $me->id)->whereNull('opened_at')->pluck('id');

        $view = view('gifts.index', ['letters' => $letters, 'cards' => $cards, 'other' => $other, 'newIds' => $unopened->all()]);
        // 打開這頁就算看過了（畫面上先標「新」，下次就不標）
        if ($unopened->isNotEmpty()) {
            Gift::whereIn('id', $unopened)->update(['opened_at' => now()]);
        }

        return $view;
    }

    public function create(Request $request, User $user): View
    {
        $this->authorizeRecipient($request->user(), $user);

        return view('gifts.create', [
            'member' => $user,
            'items' => Gift::ITEMS,
            'balance' => (int) $request->user()->fresh()->points,
            'cost' => Points::COSTS['gift'][0],
            'sentToday' => $this->sentToday($request->user(), $user),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse|View
    {
        $me = $request->user();
        $this->authorizeRecipient($me, $user);
        // 確認頁按「回去修改」：帶著剛剛選的回到表單
        if ($request->input('step') === 'edit') {
            return redirect()->route('gifts.create', $user)->with('gift_edit', true)->withInput([
                'item' => (int) $request->input('item'),
                'message' => mb_substr((string) $request->input('message'), 0, 20), // 還沒驗證，先截到 20 字再放進 session
            ]);
        }
        $data = $request->validate([
            'item' => ['required', 'integer', 'in:' . implode(',', array_keys(Gift::ITEMS))],
            'message' => ['nullable', 'string', 'max:20'],
        ], [
            'item.required' => '請選擇禮物。',
            'item.in' => '請選擇禮物。',
            'message.max' => '簡訊要在 20 個字以內。',
        ], ['item' => '禮物', 'message' => '簡訊']);

        if ($this->sentToday($me, $user)) {
            throw ValidationException::withMessages(['item' => '今天已經送過禮物給 ' . $user->name . ' 了，明天再送吧。']);
        }

        // 送出前再確認一次（會員反映誤觸就送出、沒寫到簡訊；熊叔叔 11/2 選 gift_both）：
        // 第一次送出先看確認頁，在確認頁按「確定送出」（step=send）才真的送、扣點
        if ($request->input('step') !== 'send') {
            $balance = (int) $me->fresh()->points;
            if ($balance < Points::COSTS['gift'][0]) {
                throw ValidationException::withMessages(['item' => $this->notEnoughPointsMessage()]);
            }

            return view('gifts.confirm', [
                'member' => $user,
                'item' => (int) $data['item'],
                'itemName' => Gift::ITEMS[(int) $data['item']],
                'message' => trim((string) ($data['message'] ?? '')),
                'balance' => $balance,
                'cost' => Points::COSTS['gift'][0],
            ]);
        }

        try {
            DB::transaction(function () use ($me, $user, $data) {
                $gift = new Gift;
                $gift->forceFill([
                    'sender_id' => $me->id, 'recipient_id' => $user->id, 'item' => (int) $data['item'],
                    'message' => trim((string) ($data['message'] ?? '')), 'sent_on' => now()->toDateString(),
                ])->save();
                Points::spend($me, 'gift', 'gift:' . $gift->id, '送禮物給 ' . $user->name); // 不夠會丟例外，禮物也一起不存
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['item' => '今天已經送過禮物給 ' . $user->name . ' 了，明天再送吧。']);
        } catch (NotEnoughPoints) {
            throw ValidationException::withMessages(['item' => $this->notEnoughPointsMessage()]);
        }

        defer(fn () => WebPush::notify($user)); // 有開推播的裝置
        if ($user->notify_email) { // 帳號設定「Email 通知」（舊站 gift.php「禮物通知」信）
            defer(fn () => $user->notify(new NewActivity('一份新禮物', route('gifts.index'))));
        }

        // 對方也送過我：互送禮物 → 普通好友
        $msg = sprintf('禮物送給 %s 了。', $user->name);
        if (Friendship::between($me, $user) === null
            && Gift::where('sender_id', $user->id)->where('recipient_id', $me->id)->exists()) {
            Friendship::raise($me, $user, 1);
            $msg = sprintf('您和 %s 交換禮物成功，成為「普通好友」了！', $user->name);
        }

        return redirect()->route('members.show', $user)->with('status', $msg);
    }

    // 不能送給自己、有封鎖關係的、還沒驗證或沒有檔案的人（跟傳紙條同一套）；送的人自己也要有檔案（舊站 gift.php 要先建檔）
    private function authorizeRecipient(User $me, User $user): void
    {
        abort_unless(NoteController::canNote($me, $user), 404);
        abort_unless($me->profile()->exists(), 404);
    }

    private function notEnoughPointsMessage(): string
    {
        return sprintf('愛諾積點不夠（送禮物要 %d 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。', Points::COSTS['gift'][0]);
    }

    private function sentToday(User $me, User $user): bool
    {
        return Gift::where('sender_id', $me->id)->where('recipient_id', $user->id)
            ->whereDate('sent_on', now()->toDateString())->exists();
    }
}
