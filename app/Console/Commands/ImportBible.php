<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// 匯入和合本經文：php artisan bible:import <舊站 bible/cniv.sql 的路徑>
// 檔裡每節一行 INSERT INTO `cniv` VALUES ('id','書卷代碼','章','節','經文','…','…');
// 書卷代碼照舊站 cniv_book 的順序對到 1～66。可以重跑（先清空再匯入）。
class ImportBible extends Command
{
    protected $signature = 'bible:import {path}';

    protected $description = '匯入舊站和合本經文（bible/cniv.sql）到 bible_verses';

    private const CODES = ['GEN', 'EXO', 'LEV', 'NUM', 'DEU', 'JOS', 'JUG', 'RUT', '1SA', '2SA', '1KI', '2KI', '1CH', '2CH', 'EZR', 'NEH', 'EST',
        'JOB', 'PSM', 'PRO', 'ECC', 'SON', 'ISA', 'JER', 'LAM', 'EZE', 'DAN', 'HOS', 'JOE', 'AMO', 'OBA', 'JON', 'MIC', 'NAH', 'HAB', 'ZEP',
        'HAG', 'ZEC', 'MAL', 'MAT', 'MAK', 'LUK', 'JHN', 'ACT', 'ROM', '1CO', '2CO', 'GAL', 'EPH', 'PHL', 'COL', '1TS', '2TS', '1TI', '2TI',
        'TIT', 'MON', 'HEB', 'JAS', '1PE', '2PE', '1JN', '2JN', '3JN', 'JUD', 'REV'];

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_readable($path)) {
            $this->error("讀不到 {$path}");

            return self::FAILURE;
        }
        $book = array_flip(self::CODES);
        $rows = [];
        $skipped = 0;
        foreach (new \SplFileObject($path) as $line) {
            if (! preg_match("/^INSERT INTO `cniv` VALUES \\('\\d+', '(\\w+)', '(\\d+)', '(\\d+)', '((?:[^'\\\\]|\\\\.|'')*)'/u", $line, $m)) {
                continue;
            }
            if (! isset($book[$m[1]])) {
                $skipped++;
                continue;
            }
            $text = str_replace(["\\'", "''", '(金秀)'], ["'", "'", '銹'], $m[4]); // (金秀)＝舊站打不出的「銹」（結 24:6 等 7 處）
            $rows[] = ['book' => $book[$m[1]] + 1, 'chapter' => (int) $m[2], 'verse' => (int) $m[3], 'text' => trim($text)];
        }
        if (count($rows) < 30000) {
            $this->error('只讀到 ' . count($rows) . ' 節，不像整本聖經，沒有匯入');

            return self::FAILURE;
        }
        DB::transaction(function () use ($rows) {
            DB::table('bible_verses')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('bible_verses')->insertOrIgnore($chunk);
            }
        });
        $this->info('匯入 ' . DB::table('bible_verses')->count() . ' 節（略過不認得的書卷 ' . $skipped . ' 行）');

        return self::SUCCESS;
    }
}
