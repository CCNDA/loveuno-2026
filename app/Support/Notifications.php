<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\DiaryComment;
use App\Models\Gift;
use App\Models\Meetup;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Carbon;

// 頁首通知鈴點開的浮動框（設計稿 Info_Description 819:11123；熊叔叔 2026-11-05 TG「右上角通知可以點開浮動的資訊」）：
// 系統公告、收到紙條、別人在我的日記留言、收到禮物、約會邀請、好友生日。只看最近 14 天、最多 10 則，新的在前。
class Notifications
{
    public const DAYS = 14;

    public const LIMIT = 10;

    // 回傳 [['kind', 'at' => Carbon, 'unread' => bool, 'user' => ?User, 'text', 'action', 'url'], ...]
    public static function for(User $me): array
    {
        $blocked = $me->blockRelatedIds();
        $since = now()->subDays(self::DAYS);
        $items = [];

        $news = Announcement::published()->where('published_at', '>=', $since)->latest('published_at')->first();
        if ($news) {
            $items[] = ['kind' => 'news', 'at' => $news->published_at, 'unread' => false, 'user' => null,
                'text' => $news->title, 'action' => '看公告', 'url' => route('news.show', $news)];
        }
        foreach (Note::with('sender')->where('recipient_id', $me->id)->whereNotIn('sender_id', $blocked)
            ->where('created_at', '>=', $since)->latest()->limit(5)->get() as $n) {
            $items[] = ['kind' => 'note', 'at' => $n->created_at, 'unread' => $n->read_at === null, 'user' => $n->sender,
                'text' => $n->sender->name . ' 傳了紙條給你。', 'action' => '查看紙條', 'url' => route('notes.show', $n->sender)];
        }
        foreach (DiaryComment::with('user', 'diary')->whereHas('diary', fn ($q) => $q->where('user_id', $me->id))
            ->where('user_id', '!=', $me->id)->whereNotIn('user_id', $blocked)
            ->where('created_at', '>=', $since)->latest()->limit(5)->get() as $c) {
            $items[] = ['kind' => 'comment', 'at' => $c->created_at, 'unread' => false, 'user' => $c->user,
                'text' => $c->user->name . ' 回應了你的日記〈' . $c->diary->title . '〉。', 'action' => '查看留言',
                'url' => route('diaries.show', $c->diary) . '#comments-title'];
        }
        foreach (Gift::with('sender')->where('recipient_id', $me->id)->whereNotIn('sender_id', $blocked)
            ->where('created_at', '>=', $since)->latest()->limit(5)->get() as $g) {
            $items[] = ['kind' => 'gift', 'at' => $g->created_at, 'unread' => $g->opened_at === null, 'user' => $g->sender,
                'text' => $g->sender->name . ' 送了一份禮物給你。', 'action' => '快來看看', 'url' => route('gifts.index')];
        }
        // 對方刪除了我送的、還沒回禮的禮物（熊叔叔 11/7）：通知我積點已退回
        foreach (Gift::with('recipient')->where('sender_id', $me->id)->whereNotNull('refunded_at')->whereNotIn('recipient_id', $blocked)
            ->where('refunded_at', '>=', $since)->latest('refunded_at')->limit(3)->get() as $g) {
            $items[] = ['kind' => 'gift_refund', 'at' => $g->refunded_at, 'unread' => false, 'user' => $g->recipient,
                'text' => $g->recipient->name . ' 沒有收下你的禮物，' . Points::COSTS['gift'][0] . ' 點已退回。', 'action' => '看積點紀錄',
                'url' => route('center.points')];
        }
        foreach (Meetup::with('sender')->where('recipient_id', $me->id)->where('status', 'pending')
            ->where('meet_on', '>', today())->whereNotIn('sender_id', $blocked)->latest()->limit(3)->get() as $m) {
            $items[] = ['kind' => 'meetup', 'at' => $m->created_at, 'unread' => true, 'user' => $m->sender,
                'text' => $m->sender->name . ' 邀請你約會。', 'action' => '去回覆', 'url' => route('meetups.index')];
        }
        foreach (Occasions::for($me) as $o) {
            if ($o['type'] !== 'birthday') {
                continue;
            }
            $items[] = ['kind' => 'birthday', 'at' => now()->startOfDay(), 'unread' => false, 'user' => $o['friend'],
                'text' => '你的好友 ' . $o['friend']->name . ' 生日快到了。', 'action' => '送份禮物吧',
                'url' => route('gifts.create', $o['friend'])];
        }

        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($items, 0, self::LIMIT);
    }

    // 鈴上的數字：還沒讀的紙條＋還沒拆的禮物＋等回覆的約會邀請
    public static function badgeCount(User $me): int
    {
        return Note::unreadCountFor($me) + Gift::unopenedCountFor($me)
            + Meetup::where('recipient_id', $me->id)->where('status', 'pending')->where('meet_on', '>', today())->whereNull('recipient_deleted_at')
                ->whereNotIn('sender_id', $me->blockRelatedIds())->count();
    }

    // 設計稿：「大約 3 分鐘前」「大約 1 小時前」，一天以上寫日期「12月9日」
    public static function ago(Carbon $at): string
    {
        $s = max(0, now()->getTimestamp() - $at->getTimestamp());

        return match (true) {
            $s < 60 => '剛剛',
            $s < 3600 => '大約 ' . intdiv($s, 60) . ' 分鐘前',
            $s < 86400 => '大約 ' . intdiv($s, 3600) . ' 小時前',
            default => $at->format('n月j日'),
        };
    }
}
