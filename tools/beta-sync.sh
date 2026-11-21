#!/bin/bash
# 新版愛諾園公測：舊站 → 新版同步，再把新版的修改回寫舊站（熊叔叔 11/18 選乙「含回寫」、11/20 選丙；Asana 1220564106252789）
# vm10 ubuntu 的 crontab：
#   */15 * * * * /home/ubuntu/loveuno-2026/tools/beta-sync.sh inc  >> /home/ubuntu/lu2026-sync/sync.log 2>&1
#   30 4 * * *   /home/ubuntu/loveuno-2026/tools/beta-sync.sh full >> /home/ubuntu/lu2026-sync/sync.log 2>&1
# inc ：舊站 basic.modify_date ≥（上次開始時間－5 分鐘）的會員：帳號暱稱、個人資料、日記（含舊站刪掉的）；
#       回覆、代禱、留言板、約會、見證用各自的時間欄。第一次全量匯入之後，要把「全量開始的台北時間」寫進 $STATE/last_inc 才會開始跑
# full：好友全量對帳（舊站 relationship 沒有時間欄，增量看不到）
# 每一輪先跑一次回寫（先把新版的改動送出去，再匯入；小布 11/21 審查 🟠2），最後再跑一次；回寫有 failed（要人看）→ 印 🔴、exit 3
# 舊站帳密含 " 會直接拒絕；含 \ 的會被 MySQL 設定檔當跳脫字元 → 連線失敗（不會靜靜做錯；小布 🟡5）
# 時間：舊站 PHP 用 Asia/Taipei 寫 modify_date 字串 → since 也用台北時間
# 帳密不放這裡：從新版 .env 的 LEGACY_DB_*（lu2026_legacy，舊站只能讀＋回寫那幾欄）臨時產生 600 的設定檔，跑完刪掉
# 退出碼：0 成功；1 失敗；2 用法；3 回寫有 failed
set -uo pipefail
MODE=${1:-}
APP=${LU_APP:-/home/ubuntu/loveuno-2026}
STATE=${LU_SYNC_STATE:-/home/ubuntu/lu2026-sync}
OLDWEB=${LU_OLD_WEB:-/home/loveuno}
OLDDB=${LU_OLD_DB:-austwoon_unomember}
umask 077
log() { echo "$(date '+%F %T') $*"; }
case "$MODE" in inc|full) ;; *) echo "用法：beta-sync.sh inc|full"; exit 2 ;; esac
[[ "$OLDDB" =~ ^[A-Za-z0-9_]+$ ]] || { log "🔴 LU_OLD_DB 不合規"; exit 2; }
mkdir -p "$STATE" || { log "🔴 建不了 $STATE"; exit 1; }
exec 9>"$STATE/lock"
flock -n 9 || { log "⏭ $MODE：上一輪還在跑，這輪跳過"; exit 0; }

cnf=$(mktemp) && out=$(mktemp -d) || { log "🔴 建不了暫存檔"; exit 1; }
trap 'rm -rf "$cnf" "$out"' EXIT
# 舊站連線設定 → 臨時 cnf（值不印；有雙引號的密碼 parse_ini_file 讀不了，直接失敗）
if ! (cd "$APP" && php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c = config("database.connections.legacy");
    if (($c["username"] ?? "") === "" || ($c["password"] ?? "") === "" || str_contains($c["username"] . $c["password"], "\"")) { exit(1); }
    file_put_contents($argv[1], "[client]\nuser=\"{$c["username"]}\"\npassword=\"{$c["password"]}\"\nhost=127.0.0.1\n");
  ' "$cnf"); then
  log "🔴 $MODE：讀不到舊站連線設定（.env 的 LEGACY_DB_USERNAME／PASSWORD）"; exit 1
fi

run() { # run <說明> <指令...>：失敗印最後幾行、exit 1
  local what=$1; shift
  if ! "$@" > "$out/run.log" 2>&1; then
    log "🔴 $MODE：$what 失敗"; tail -5 "$out/run.log"; exit 1
  fi
  log "   $what：$(grep -vE '^\s*$' "$out/run.log" | tail -1)"
}

(cd "$APP" && php artisan legacy:push-outbox) > "$out/push0.log" 2>&1
log "   先回寫：$(grep -vE '^\s*$' "$out/push0.log" | tail -1)"

if [ "$MODE" = inc ]; then
  now=$(TZ=Asia/Taipei date '+%F %T')
  last=$(cat "$STATE/last_inc" 2>/dev/null || true)
  if ! [[ "$last" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}\ [0-9]{2}:[0-9]{2}:[0-9]{2}$ ]]; then
    log "🔴 inc：$STATE/last_inc 沒有或格式不對（第一次全量匯入後寫入那次開始的台北時間）"; exit 1
  fi
  since=$(TZ=Asia/Taipei date -d "$last 5 minutes ago" '+%F %T') || { log "🔴 inc：算不出 since"; exit 1; }
  log "inc：since $since"
  run "匯出帳號" php "$APP/tools/legacy_export.php" --cnf="$cnf" --db="$OLDDB" --out="$out/acc" --web="$OLDWEB" --since="$since"
  run "匯出內容" php "$APP/tools/legacy_export_content.php" --cnf="$cnf" --db="$OLDDB" --out="$out/con" --web="$OLDWEB" --kind=diary,pray,guestbook,date,testi --since="$since"
  run "匯入帳號" bash -c "cd '$APP' && php artisan legacy:import-export '$out/acc' --update-from-legacy"
  run "匯入內容" bash -c "cd '$APP' && php artisan legacy:import-content '$out/con' --update-from-legacy"
  echo "$now" > "$STATE/last_inc.tmp" && mv "$STATE/last_inc.tmp" "$STATE/last_inc"
else
  log "full：好友對帳"
  run "匯出好友" php "$APP/tools/legacy_export_content.php" --cnf="$cnf" --db="$OLDDB" --out="$out/con" --web="$OLDWEB" --kind=friend
  run "好友對帳" bash -c "cd '$APP' && php artisan legacy:import-content '$out/con' --update-from-legacy"
fi

# 回寫：新版 → 舊站。出錯的那筆會自己重試（5 次後 failed）
(cd "$APP" && php artisan legacy:push-outbox) > "$out/push.log" 2>&1
log "   回寫：$(grep -vE '^\s*$' "$out/push.log" | tail -1)"
failed=$(cd "$APP" && php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Illuminate\Support\Facades\DB::table("legacy_outbox")->where("status", "failed")->count();
  ' 2>/dev/null | tail -1)
if ! [[ "$failed" =~ ^[0-9]+$ ]]; then
  log "🔴 $MODE：查不到回寫失敗筆數"; exit 1
fi
if [ "$failed" -gt 0 ]; then
  log "🔴 回寫有 $failed 筆 failed（同一筆後面的會一直等）：要人看 last_error，處理完改 skipped"; exit 3
fi
log "✅ $MODE 完成"
