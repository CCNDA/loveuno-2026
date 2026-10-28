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
}
