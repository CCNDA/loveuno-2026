<?php

namespace App\Models;

use App\Models\Concerns\HiddenPerSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 禮物。所有欄位由 GiftController 指定，沒有表單直接寫進來，所以不開 Fillable。
class Gift extends Model
{
    use HiddenPerSide;

    // 設計稿的 12 種，照 SendGiftDialog.tsx 的 id 與順序（熊叔叔 10/29「2A」）；名字以 Figma 為準（第 4 種 Figma 是
    // 「恩膏葡萄酒」，前台程式寫「恩賜葡萄酒」）。圖是 Figma 01_UNO_Website「gift」框（330:9182）匯出的，
    // 縮成 192px 的 WebP 放在 public/images/gifts/<id>.webp（五餅二魚那一張第一份不完整，用第二份 342:39614）。
    public const ITEMS = [
        1 => '雲彩圍繞',
        2 => '堅固房角石',
        3 => '恩雨滋潤',
        4 => '恩膏葡萄酒',
        5 => '守護者天使',
        6 => '聖經的奧秘',
        7 => '雲上太陽',
        8 => '聖靈充滿',
        9 => '五餅二魚',
        10 => '忠心小驢駒',
        11 => '主的小羊',
        12 => '初熟羊肉爐',
    ];

    protected function casts(): array
    {
        return [
            'sender_id' => 'integer',
            'recipient_id' => 'integer',
            'item' => 'integer',
            'sent_on' => 'date',
            'opened_at' => 'datetime',
            'sender_deleted_at' => 'datetime',
            'recipient_deleted_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public static function imageUrl(int $item): string
    {
        return asset('images/gifts/' . (isset(self::ITEMS[$item]) ? $item : 1) . '.webp');
    }

    public function itemName(): string
    {
        return self::ITEMS[$this->item] ?? '禮物';
    }

    public static function unopenedCountFor(User $user): int
    {
        return self::where('recipient_id', $user->id)->whereNull('opened_at')->whereNull('recipient_deleted_at')
            ->whereNotIn('sender_id', $user->blockRelatedIds())->count();
    }
}
