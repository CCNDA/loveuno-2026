<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// 查經文（和合本）：書卷用中文名（ProfileOptions::BIBLE_BOOKS 的名字），查不到回 null
class Bible
{
    public static function bookNumber(string $name): ?int
    {
        $i = array_search($name, ProfileOptions::BIBLE_BOOKS, true);

        return $i === false ? null : $i + 1;
    }

    public static function verse(string $book, int $chapter, int $verse): ?string
    {
        $n = self::bookNumber($book);
        if (! $n || $chapter < 1 || $verse < 1) {
            return null;
        }
        $text = DB::table('bible_verses')->where(['book' => $n, 'chapter' => $chapter, 'verse' => $verse])->value('text');

        return $text === null ? null : (string) $text;
    }
}
