# loveuno-2026 — 愛諾園改版（2026）

社團法人中華基督教網路發展協會（CCNDA）「愛諾園」會員網站的改版專案。

- 技術：PHP（Laravel）+ MariaDB + REST API
- 沿用舊版多數功能（會員、點數、配對／私人約會、心情日記等），差異部分依開發調整。
- 無障礙：新開發依無障礙規範，驗收標準 Lighthouse 100 ＋鍵盤操作 ＋ NVDA。

## 環境鏈

- 測試站：loveuno.ps194.net
- 公測站：2026.loveuno.com
- 正式站：loveuno.com（上線後）

## 開發

需 PHP 8.2+ 與 Composer。

```
composer install
cp .env.example .env
php artisan key:generate
# 設定 .env 的 DB 連線（MariaDB）後：
php artisan migrate
```

## 設定與機密

所有機密（DB、Cloudflare R2、郵件等）一律放在 `.env`（已於 `.gitignore` 排除），不進版控；範本見 `.env.example`。

版本號見 `VERSION`。
