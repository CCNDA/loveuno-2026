<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Support\Bible;
use App\Support\LegacyText;
use Illuminate\Console\Command;

// 舊站搬過來的個人資料補正（熊叔叔 11/6 TG）：
// 1 自我介紹裡的 <br>、&nbsp; 轉成純文字
// 2 最愛經句有書卷章節、卻沒有經文的（搬家時還沒有經文庫）：從經文庫補上
// php artisan profiles:fix-legacy-text [--dry-run]
class FixLegacyProfileText extends Command
{
    protected $signature = 'profiles:fix-legacy-text {--dry-run : 只印要改幾筆，不存}';

    protected $description = '舊站個人資料：自我介紹去掉 HTML、補最愛經句的經文';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $intro = 0;
        $verse = 0;
        Profile::query()->where(fn ($q) => $q->where('intro', 'like', '%<%')->orWhere('intro', 'like', '%&%'))
            ->each(function (Profile $p) use ($dry, &$intro) {
                $clean = LegacyText::plain($p->intro);
                if ($clean !== $p->intro) {
                    $intro++;
                    if (! $dry) {
                        $p->forceFill(['intro' => $clean])->saveQuietly();
                    }
                }
            });
        Profile::query()->where('verse_book', '!=', '')->where(fn ($q) => $q->whereNull('verse_text')->orWhere('verse_text', ''))
            ->each(function (Profile $p) use ($dry, &$verse) {
                $text = Bible::verse((string) $p->verse_book, (int) $p->verse_chapter, (int) $p->verse_verse);
                if ($text !== null) {
                    $verse++;
                    if (! $dry) {
                        $p->forceFill(['verse_text' => $text])->saveQuietly();
                    }
                }
            });
        $this->line(($dry ? '（乾跑）' : '') . "自我介紹去 HTML：{$intro} 筆；補最愛經句經文：{$verse} 筆");

        return self::SUCCESS;
    }
}
