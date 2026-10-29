<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Gift;
use App\Models\User;
use App\Support\Points;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 送禮物（設計稿 SendGiftDialog；舊站 member/gift.php）：
// 一次 3 點、同一位會員一天一份、簡訊 20 字內；對方也送過我禮物，就成為「普通好友」（熊叔叔 10/29「1A」）。
class GiftController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $blocked = $me->blockRelatedIds();
        $received = Gift::with('sender.profile')->where('recipient_id', $me->id)->whereNotIn('sender_id', $blocked)
            ->orderByDesc('created_at')->limit(50)->get();
        $sent = Gift::with('recipient.profile')->where('sender_id', $me->id)->whereNotIn('recipient_id', $blocked)
            ->orderByDesc('created_at')->limit(50)->get();
        $unopened = $received->whereNull('opened_at')->pluck('id');

        $view = view('gifts.index', ['received' => $received, 'sent' => $sent, 'newIds' => $unopened->all()]);
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

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $this->authorizeRecipient($me, $user);
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
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['item' => sprintf('愛諾積點不夠（送禮物要 %d 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。', Points::COSTS['gift'][0])]);
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

    // 不能送給自己、有封鎖關係的、還沒驗證或沒有檔案的人（跟傳紙條同一套）
    private function authorizeRecipient(User $me, User $user): void
    {
        abort_unless(NoteController::canNote($me, $user), 404);
    }

    private function sentToday(User $me, User $user): bool
    {
        return Gift::where('sender_id', $me->id)->where('recipient_id', $user->id)
            ->whereDate('sent_on', now()->toDateString())->exists();
    }
}
