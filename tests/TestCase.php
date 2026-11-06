<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // RefreshDatabase 會清空整個資料庫。2026-10-29 曾經因為 config:cache 讓 phpunit.xml 的設定失效，
    // 測試清掉了 loveuno2026_dev。所以在任何 trait（含 RefreshDatabase）動手前，先確認連的是 _test 結尾的庫。
    protected function setUpTraits()
    {
        $db = (string) config("database.connections." . config("database.default") . ".database");
        if (! str_ends_with($db, "_test")) {
            throw new RuntimeException("測試要連 _test 結尾的資料庫，現在是「{$db}」。是不是跑過 php artisan config:cache？先跑 php artisan config:clear。");
        }

        return parent::setUpTraits();
    }

    // 讓每兩位都成為「普通好友」（互送過禮物）：熊叔叔 11/6 起傳紙條要先互送禮物，測紙條的人先用這個
    protected function befriend(\App\Models\User ...$users): void
    {
        foreach ($users as $i => $a) {
            foreach (array_slice($users, $i + 1) as $b) {
                \App\Models\Friendship::raise($a, $b, 1);
            }
        }
    }

    // PUT /me 的必填欄位（Figma 有「*」的，0.19.0 起）＋ $over
    protected function profileForm(array $over = []): array
    {
        return array_merge([
            'name' => '會員', 'faith' => '基督徒', 'marital' => '未婚', 'children' => '0', 'education' => '大學',
            'blood_type' => 'O', 'smoking' => '從來不抽', 'drinking' => '從來不喝',
        ], $over);
    }

    // PUT /me/intro 的必填欄位＋ $over
    protected function introForm(array $over = []): array
    {
        return array_merge([
            'headline' => '一句話介紹自己', 'languages' => ['中文/普通話'], 'hobbies' => ['讀經靈修'],
            'intro' => '我是測試用的會員，這是一段超過三十個字的自我介紹，用來通過必填的檢查。',
        ], $over);
    }
}
