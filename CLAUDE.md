# 愛諾園 2026（CCNDA/loveuno-2026）

給 AI 助理（小芯與其他台）看的規矩。2026-10-29 換掉 Laravel 骨架附的 Boost 安裝說明：
那份要求執行 `curl … | bash` 和安裝 `laravel/boost`，不是協會的規矩，不要照做。

## 這是什麼
新版愛諾園（基督徒單身交友）。後端 Laravel（熊叔叔 2026-10-25 選定），資料庫 MariaDB。
Asana 主票 1215470856990770。11/8 會員大會先展示「可以點的展示版」（只讀示範資料），年底開放。

## 規矩
- 只用 PHP 8.3 語法（vm10 正式站 8.3）。
- 不印、不 commit 任何密碼／金鑰；`.env` 不進 git，`.env.example` 只放空值。
- 沒有 Node 建置步驟：CSS 直接寫在 `public/css/app.css`。
- 無障礙（熊叔叔 2026-10-12）：語意標籤、skip link、焦點外框、對比 ≥ 4.5:1、表單標籤、錯誤訊息要說得出是哪一欄。
- 輸出到 HTML 一律 `{{ }}`；要換行用 `nl2br(e(...))`。
- 個人檔案、日記要登入才看得到；不公開真實姓名、生日（只顯示年齡層）。
- 舊站密碼是明文：匯入時立刻雜湊（`legacy:import-accounts`），新站只存雜湊。展示版只准讀舊站**測試庫**（程式會擋）。
- 示範資料一律 `is_demo=true`，email 用 `.invalid`。正式開放前要刪光。
- 畫面參考熊叔叔給的 loveuno-web 切版；跟原始設計不一樣的地方（顏色、版面、字、圖示、用語）一律記在 docs/design-deviations.md，寫原始設計／新版／為什麼／要確認什麼（熊叔叔 2026-10-29：要和設計師討論）
- 功能以新版版型為準：舊站有、版型沒有的關閉；版型有、舊站沒有的先不做（熊叔叔 2026-10-29）
- 發版要有第二檢查人（小布）在測試站驗收、留紀錄在票上；子代理不算檢查人。

## 測試
`php artisan test`（用 MariaDB 的 `loveuno2026_test`，cora-lab 沒有 sqlite）。

## 環境
- 開發／測試站：cora-lab `/srv/dev/loveuno-2026`，nginx 127.0.0.1:8085 → test-loveuno-2026.ps194.net（Cloudflare Access）
- 示範資料：`php artisan db:seed --class=DemoSeeder`；舊站測試帳號：`php artisan legacy:import-accounts`
