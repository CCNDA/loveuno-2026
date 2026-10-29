<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

// 好友關係：一對會員一列（user_id < friend_id）。等級照舊站（data/relationship.php、member/profile_left.php）。
// 所有欄位都由程式指定，沒有表單直接寫進來，所以不開 Fillable。
class Friendship extends Model
{
    public const LEVELS = [
        0 => '路人甲',
        1 => '普通好友',
        2 => '聊天好友',
        3 => '真實好友',
    ];

    // 舊站 guestbook.php：雙方各留言超過 4 則（＝各 5 則以上）就升「聊天好友」。新版留言板＝傳紙條
    public const CHAT_NOTES = 5;

    protected function casts(): array
    {
        return ['level' => 'integer', 'user_id' => 'integer', 'friend_id' => 'integer', 'leveled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public static function between(User $a, User $b): ?self
    {
        [$low, $high] = self::pair($a, $b);

        return self::where('user_id', $low)->where('friend_id', $high)->first();
    }

    // 只會往上升，不會降級（降級＝先解除再重來）
    public static function raise(User $a, User $b, int $level): self
    {
        [$low, $high] = self::pair($a, $b);
        $f = self::where('user_id', $low)->where('friend_id', $high)->first();
        if ($f === null) {
            $f = new self;
            $f->forceFill(['user_id' => $low, 'friend_id' => $high, 'level' => $level, 'leveled_at' => now()]);
            try {
                $f->save();
            } catch (UniqueConstraintViolationException) {
                $f = self::where('user_id', $low)->where('friend_id', $high)->firstOrFail(); // 兩個請求同時建
            }
        }
        if ($f->level < $level) {
            $f->forceFill(['level' => $level, 'leveled_at' => now()])->save();
        }
        User::forgetFriendLevels($a, $b);

        return $f;
    }

    public static function remove(User $a, User $b): void
    {
        [$low, $high] = self::pair($a, $b);
        self::where('user_id', $low)->where('friend_id', $high)->delete();
        User::forgetFriendLevels($a, $b);
    }

    // 普通好友 → 聊天好友：雙方互傳紙條各 CHAT_NOTES 則以上
    public static function raiseByNotes(User $a, User $b): void
    {
        $f = self::between($a, $b);
        if (! $f || $f->level !== 1) {
            return;
        }
        $sent = fn (User $from, User $to) => Note::where('sender_id', $from->id)->where('recipient_id', $to->id)->count();
        if ($sent($a, $b) >= self::CHAT_NOTES && $sent($b, $a) >= self::CHAT_NOTES) {
            self::raise($a, $b, 2);
        }
    }

    public function otherThan(User $me): int
    {
        return $this->user_id === (int) $me->id ? $this->friend_id : $this->user_id;
    }

    public function label(): string
    {
        return self::LEVELS[$this->level] ?? '';
    }

    private static function pair(User $a, User $b): array
    {
        return [min((int) $a->id, (int) $b->id), max((int) $a->id, (int) $b->id)];
    }
}
