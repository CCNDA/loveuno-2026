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

## 公測站（beta.loveuno.com）專用設定

| .env | 作用 |
|---|---|
| `BETA_INVITE_ONLY=true` | 只有名單上的人（`php artisan beta:invite`）和管理員能登入 |
| `BETA_READONLY=true` | 停寫：會改資料的請求都擋（含驗證信連結、每日登入給點），只能瀏覽 |
| `BETA_AUDIT=true` | 每筆寫入記進 `beta_audits`，要回寫舊站的排進 `legacy_outbox`（`php artisan legacy:push-outbox` 送出） |
| `BETA_AUDIT_KEEP_DAYS=60` | `beta_audits` 存整筆欄位（含 email、手機），每天 03:40 `php artisan beta:prune-audits` 只留這麼多天；公測結束後整張清掉 |

回寫的已知限制（小布 11/21 審查提醒）：
- `legacy_outbox` 某一筆變成 `failed` 後，同一筆後面的會一直「等前一筆」；要人看過 `last_error`、處理完把它改成 `skipped` 才會放行。排程包裝會在 failed 筆數大於 0 時通知。
- 日記新增重送時用「同作者＋標題＋日期＋內容」找舊站已有的那篇；同一位作者同一天寫兩篇一模一樣的，會共用同一個舊站編號。
- 停權只同步一個方向：舊站停權 → 新版停權；舊站解除停權不會自動解除，要在新版後台手動解除。
- `tools/beta-sync.sh` 從 `.env` 的 `LEGACY_DB_*` 臨時產生 MySQL 設定檔：密碼含 `"` 會直接拒絕，含 `\` 會被當跳脫字元而連線失敗（換密碼時避開這兩個字元）。
- 同步順序：每輪先回寫（新版 → 舊站）再匯入（舊站 → 新版），最後再回寫一次；會員在新版刪掉的日記、解除的好友，匯入時不會再加回來。
