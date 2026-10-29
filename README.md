# loveuno-2026 — 愛諾園改版（2026）

社團法人中華基督教網路發展協會（CCNDA）「愛諾園」會員網站的改版專案。

- 技術：PHP（Laravel）+ MariaDB + REST API
- 沿用舊版多數功能（會員、點數、配對／私人約會、心情日記等），差異部分依開發調整。
- 無障礙：新開發依無障礙規範，驗收標準 Lighthouse 100 ＋鍵盤操作 ＋ NVDA。

## 環境鏈

- 測試站：test-loveuno-2026.ps194.net（熊叔叔 10/28 通則：test-<站名>.ps194.net）
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

## 部署（正式站、公測站）

```
php artisan migrate --force
php artisan push:vapid   # 第一次部署跑一次：產生推播金鑰寫進 .env（已經有就不動；不要換，換了所有裝置要重開推播）
php artisan view:clear && php artisan route:clear
```

.env 還要設：`SMS_DRIVER=twsms`、`TWSMS_USERNAME`、`TWSMS_PASSWORD`（手機認證，熊叔叔設定）、`LOVEUNO_CONTACT_EMAIL=support@loveuno.com`、寄信（MAIL_*）。
不要跑 `php artisan config:cache`（測試會連到錯的庫，見 tests/TestCase.php）。

## 設定與機密

所有機密（DB、Cloudflare R2、郵件等）一律放在 `.env`（已於 `.gitignore` 排除），不進版控；範本見 `.env.example`。

版本號見 `VERSION`。
