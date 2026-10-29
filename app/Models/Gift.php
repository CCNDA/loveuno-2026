<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 禮物。所有欄位由 GiftController 指定，沒有表單直接寫進來，所以不開 Fillable。
class Gift extends Model
{
    // 設計稿 components/dialog/SendGiftDialog.tsx 的 12 種，照原本的 id 與順序（熊叔叔 10/29「2A」）。
    // 圖還沒有（設計稿是暫放的圖），先都用 gift.svg，等 Figma 的圖。
    public const ITEMS = [
        1 => '雲彩圍繞',
        2 => '堅固房角石',
        3 => '恩雨滋潤',
        4 => '恩賜葡萄酒',
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

    public function itemName(): string
    {
        return self::ITEMS[$this->item] ?? '禮物';
    }

    public static function unopenedCountFor(User $user): int
    {
        return self::where('recipient_id', $user->id)->whereNull('opened_at')
            ->whereNotIn('sender_id', $user->blockRelatedIds())->count();
    }
}
