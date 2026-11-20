#!/bin/bash
# 新版愛諾園公測 beta.loveuno.com：vm10 一次性設定（要 root；熊叔叔跑）——第二版（小布 11/20 15:43 審查 2 紅 2 橙 1 黃）
# 熊叔叔 11/17：跑 vm10、網址 beta.loveuno.com、新建 loveuno2026 庫；11/18 選「乙 11/22 含回寫全部做完」；11/20 選「丙 照原訂 11/22 全部做完」
# Asana 1220564106252789（同步）、1220586286066431（停寫／備份）、1220456570088705（決策地圖）
#
# 安全原則（第二版）：
#   - ubuntu 寫得動的地方（/home/ubuntu 底下）一律用 runuser -u ubuntu 讀寫，root 不碰 → 符號連結換不到 root 權限（小布 🔴1）
#   - 舊站 config.php 只用 grep 讀字面值，不執行（小布 🔴2）
#   - 密碼只走 stdin（printf 是 shell 內建，不會出現在任何程序的命令列；小布 🟠3）
#   - root 的暫存檔一律 mktemp（小布 🟠4）
#   - 回寫帳號只開需要的欄位／表（小布 🟡5）
#
# 做什麼（任何一步失敗就回復已做的，不留半套）：
#   1. 從舊站 /home/loveuno/h2019/config.php 讀出兩個舊庫「名字」（字面值），找出 basic、profile、diary、relationship 各在哪個庫
#   2. 建 loveuno2026 庫（utf8mb4）＋三個帳號（密碼隨機產生、不印）：
#        lu2026_app     新版程式：loveuno2026 全權
#        lu2026_backup  每日備份：loveuno2026 只讀＋鎖表
#        lu2026_legacy  同步與回寫：兩個舊庫只讀；回寫只開
#                         basic 的 nickname、modify_date 兩欄 UPDATE（email、密碼、狀態不能改）
#                         profile UPDATE（個人資料，沒有帳號欄位）
#                         diary INSERT、UPDATE（新版刪日記 → 舊站改成隱藏，不刪）
#                         relationship INSERT、UPDATE、DELETE（新版解除好友 → 舊站刪那一列）
#   3. 帳密以 ubuntu 身分寫進 /home/ubuntu/loveuno-2026/.env（640）與 /home/ubuntu/.my.cnf 的 [mysqldump] 段（600）
#   4. storage／bootstrap/cache 給 www-data 寫（只改不是連結的檔、chgrp -h）；.env 群組改 www-data
#   5. 裝 nginx 站台 beta.loveuno.com（設定檔以 ubuntu 身分讀進 root 暫存、驗寫死的雜湊才裝）→ nginx -t → reload
# 用法：
#   sudo bash /tmp/vm10-beta-setup.sh            做
#   sudo bash /tmp/vm10-beta-setup.sh --undo     收回：拿掉 nginx 站台、刪三個帳號；資料庫 loveuno2026 保留
# 退出碼：0 完成；1 沒有做（或已完整回復），訊息寫卡在哪一步；2 🔴 回復失敗或做到一半，要馬上看
set -u
PATH=/usr/sbin:/usr/bin:/sbin:/bin
umask 077

NGINX_SHA=8eb77c3c835fb02b26630bbb25d2859b3323e436539d6f607196a08811123f9e
APP=/home/ubuntu/loveuno-2026
ENVF=$APP/.env
CONF_SRC=$APP/deploy/nginx/beta.loveuno.com.conf
SITE=/etc/nginx/sites-available/beta.loveuno.com
LINK=/etc/nginx/sites-enabled/beta.loveuno.com
OLDCFG=/home/loveuno/h2019/config.php
MYCNF=/home/ubuntu/.my.cnf
DB=loveuno2026
USERS="lu2026_app lu2026_backup lu2026_legacy"

[ "$(id -u)" -eq 0 ] || { echo "沒有做（1）：要用 sudo 執行"; exit 1; }
MODE=${1:-}
[ "$#" -le 1 ] && { [ -z "$MODE" ] || [ "$MODE" = "--undo" ]; } || { echo "沒有做（1）：不認得的參數"; exit 1; }
sql() { mariadb -N -B -e "$1"; }
user_exists() { [ "$(sql "SELECT COUNT(*) FROM mysql.user WHERE User='$1' AND Host='localhost'")" = 1 ]; }
as_ubuntu() { runuser -u ubuntu -- "$@"; }

if [ "$MODE" = "--undo" ]; then
  rc=0
  rm -f "$LINK" "$SITE"
  if [ -e "$LINK" ] || [ -L "$LINK" ] || [ -e "$SITE" ]; then echo "🔴 nginx 站台檔刪不掉"; rc=2; fi
  if nginx -t >/dev/null 2>&1; then systemctl reload nginx && echo "nginx 站台已拿掉、已 reload"; else echo "🔴 拿掉站台後 nginx -t 失敗"; rc=2; fi
  for u in $USERS; do sql "DROP USER IF EXISTS '$u'@'localhost'" || rc=2; user_exists "$u" && { echo "🔴 帳號 $u 還在"; rc=2; }; done
  [ $rc = 0 ] && echo "已收回（0）：站台與三個帳號都拿掉了；資料庫 $DB 保留" || echo "🔴 收回沒有完全成功（2）"
  exit $rc
fi

echo "== 0. 檢查"
[ -d "$APP" ] && [ ! -L "$APP" ] && [ "$(stat -c %U "$APP")" = ubuntu ] && as_ubuntu test -f "$APP/artisan" || { echo "沒有做（1）：$APP 還沒部署（小芯先 git clone）"; exit 1; }
# 事先擋連結（之後的寫入本來就是 ubuntu 身分、跟著連結也只有 ubuntu 的權限；這裡是讓它在動資料庫之前就停）
for f in "$ENVF" "$MYCNF"; do as_ubuntu test -L "$f" && { echo "沒有做（1）：$f 是符號連結"; exit 1; }; done
as_ubuntu test -f "$ENVF" || { echo "沒有做（1）：$ENVF 不存在（小芯先放好不含帳密的 .env）"; exit 1; }
for d in storage bootstrap bootstrap/cache; do [ -d "$APP/$d" ] && [ ! -L "$APP/$d" ] || { echo "沒有做（1）：$APP/$d 不是一般資料夾"; exit 1; }; done
{ [ -e "$SITE" ] || [ -e "$LINK" ] || [ -L "$LINK" ]; } && { echo "沒有做（1）：beta 站台已經存在（已經跑過？）"; exit 1; }
for u in $USERS; do user_exists "$u" && { echo "沒有做（1）：帳號 $u 已經存在（已經跑過？）"; exit 1; }; done
as_ubuntu grep -q '^\[mysqldump\]' "$MYCNF" 2>/dev/null && { echo "沒有做（1）：$MYCNF 已經有 [mysqldump] 段"; exit 1; }
nginx -t >/dev/null 2>&1 || { echo "沒有做（1）：現在的 nginx 設定就有錯，先不要動"; exit 1; }
sql "SELECT 1" >/dev/null || { echo "沒有做（1）：root 連不上 MariaDB"; exit 1; }
DB_EXISTED=0; [ "$(sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB'")" = 1 ] && DB_EXISTED=1
if [ $DB_EXISTED = 1 ] && [ "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB'")" != 0 ]; then
  echo "沒有做（1）：$DB 已經存在而且有資料表"; exit 1
fi

NC=$(mktemp) && SQLF=$(mktemp) && ERRF=$(mktemp) || { echo "沒有做（1）：建不了暫存檔"; exit 1; }
trap 'rm -f "$NC" "$SQLF" "$ERRF"' EXIT
# nginx 設定：以 ubuntu 身分讀（root 不跟 ubuntu 的連結走），進 root 暫存後驗雜湊
as_ubuntu cat "$CONF_SRC" > "$NC" 2>/dev/null && [ "$(sha256sum "$NC" | cut -d' ' -f1)" = "$NGINX_SHA" ] \
  || { echo "沒有做（1）：nginx 設定檔不是審過的那一版（雜湊不符）"; exit 1; }

echo "== 1. 舊站資料庫（只讀字面值，不執行 config.php）"
oldname() { grep -oE "['\"]$1['\"][[:space:]]*=>[[:space:]]*['\"][A-Za-z0-9_]+['\"]" "$OLDCFG" 2>/dev/null | head -1 | sed -E "s/.*=>[[:space:]]*['\"]([A-Za-z0-9_]+)['\"]/\1/"; }
OLD1=$(oldname dbname); OLD2=$(oldname dbname2)
for n in "$OLD1" "$OLD2"; do [[ "$n" =~ ^[A-Za-z0-9_]+$ ]] || { echo "沒有做（1）：讀不到舊站資料庫名（$OLDCFG）"; exit 1; }; done
[ "$OLD1" != "$OLD2" ] || { echo "沒有做（1）：兩個舊庫名一樣"; exit 1; }
echo "  舊庫：$OLD1、$OLD2"
declare -A LOC
for t in basic profile diary relationship; do
  s=$(sql "SELECT TABLE_SCHEMA FROM information_schema.TABLES WHERE TABLE_NAME='$t' AND TABLE_SCHEMA IN ('$OLD1','$OLD2')")
  [ "$(printf '%s\n' "$s" | grep -c .)" = 1 ] || { echo "沒有做（1）：$t 不在兩個舊庫其中剛好一個（找到：${s:-無}）"; exit 1; }
  LOC[$t]=$s; echo "  $t 在 $s"
done
for c in nickname modify_date; do
  [ "$(sql "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='${LOC[basic]}' AND TABLE_NAME='basic' AND COLUMN_NAME='$c'")" = 1 ] \
    || { echo "沒有做（1）：basic 沒有 $c 欄"; exit 1; }
done

echo "== 2. 建庫與帳號（密碼不印）"
pw() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32; }
P_APP=$(pw); P_BK=$(pw); P_LG=$(pw)
[ ${#P_APP} = 32 ] && [ ${#P_BK} = 32 ] && [ ${#P_LG} = 32 ] || { echo "沒有做（1）：產生密碼失敗"; exit 1; }
cat > "$SQLF" <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lu2026_app'@'localhost' IDENTIFIED BY '$P_APP';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO 'lu2026_app'@'localhost';
CREATE USER 'lu2026_backup'@'localhost' IDENTIFIED BY '$P_BK';
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT ON \`$DB\`.* TO 'lu2026_backup'@'localhost';
CREATE USER 'lu2026_legacy'@'localhost' IDENTIFIED BY '$P_LG';
GRANT SELECT ON \`$OLD1\`.* TO 'lu2026_legacy'@'localhost';
GRANT SELECT ON \`$OLD2\`.* TO 'lu2026_legacy'@'localhost';
GRANT UPDATE (nickname, modify_date) ON \`${LOC[basic]}\`.\`basic\` TO 'lu2026_legacy'@'localhost';
GRANT UPDATE ON \`${LOC[profile]}\`.\`profile\` TO 'lu2026_legacy'@'localhost';
GRANT INSERT, UPDATE ON \`${LOC[diary]}\`.\`diary\` TO 'lu2026_legacy'@'localhost';
GRANT INSERT, UPDATE, DELETE ON \`${LOC[relationship]}\`.\`relationship\` TO 'lu2026_legacy'@'localhost';
EOF
undo_db() {
  local ok=0
  for u in $USERS; do sql "DROP USER IF EXISTS '$u'@'localhost'" || ok=1; user_exists "$u" && ok=1; done
  if [ $DB_EXISTED = 0 ]; then sql "DROP DATABASE IF EXISTS \`$DB\`" || ok=1; fi
  return $ok
}
if ! mariadb < "$SQLF" 2>"$ERRF"; then
  echo "  建庫或帳號失敗：$(sed -E "s/IDENTIFIED BY .*//; s/[A-Za-z0-9]{32}/***/g" "$ERRF" | head -3)"
  undo_db && { echo "沒有做（1）：已回復（帳號刪除、新建的庫刪除）"; exit 1; }
  echo "🔴 回復失敗（2）：請看 mysql.user 裡 lu2026_*"; exit 2
fi
: > "$SQLF"
echo "  ✅ 庫 $DB、三個帳號"

echo "== 3. 寫帳密（以 ubuntu 身分；密碼走 stdin）"
OTHER=$([ "${LOC[basic]}" = "$OLD1" ] && echo "$OLD2" || echo "$OLD1")
# ubuntu 跑的小程式：從 stdin 讀 KEY=VALUE，更新 .env（先寫同目錄暫存、再改名），原本的 .env 留備份
ENV_PHP='$f=$argv[1]; $set=[]; foreach (explode("\n", stream_get_contents(STDIN)) as $l) { if (preg_match("/^([A-Z0-9_]+)=(.*)$/", $l, $m)) $set[$m[1]] = $m[2]; }
if (count($set) !== 12) { fwrite(STDERR, "鍵數不對\n"); exit(1); }
if (is_link($f) || !is_file($f)) { fwrite(STDERR, ".env 不是一般檔\n"); exit(1); }
$lines = file($f, FILE_IGNORE_NEW_LINES); $seen = [];
foreach ($lines as &$l) { if (preg_match("/^([A-Z0-9_]+)=/", $l, $m) && isset($set[$m[1]])) { $l = $m[1] . "=" . $set[$m[1]]; $seen[$m[1]] = 1; } } unset($l);
foreach ($set as $k => $v) if (!isset($seen[$k])) $lines[] = "$k=$v";
if (!copy($f, $f . ".bak-" . date("Ymd-His"))) exit(1);
$tmp = tempnam(dirname($f), ".env.cora-"); if ($tmp === false) exit(1);
if (file_put_contents($tmp, implode("\n", $lines) . "\n") === false || !chmod($tmp, 0640) || !rename($tmp, $f)) { @unlink($tmp); exit(1); }'
if ! printf 'DB_CONNECTION=mariadb\nDB_HOST=localhost\nDB_PORT=3306\nDB_DATABASE=%s\nDB_USERNAME=lu2026_app\nDB_PASSWORD=%s\nLEGACY_DB_HOST=localhost\nLEGACY_DB_PORT=3306\nLEGACY_DB_DATABASE=%s\nLEGACY_DB_DATABASE2=%s\nLEGACY_DB_USERNAME=lu2026_legacy\nLEGACY_DB_PASSWORD=%s\n' \
     "$DB" "$P_APP" "${LOC[basic]}" "$OTHER" "$P_LG" | as_ubuntu php -r "$ENV_PHP" "$ENVF"; then
  undo_db && { echo "沒有做（1）：寫 .env 失敗，已回復帳號（.env 沒動）"; exit 1; }
  echo "🔴 寫 .env 失敗而且回復帳號失敗（2）"; exit 2
fi
if ! printf '[mysqldump]\nuser=lu2026_backup\npassword=%s\n' "$P_BK" | as_ubuntu sh -c 'umask 077; f=$1; [ -L "$f" ] && exit 1; { [ -s "$f" ] && echo; cat; } >> "$f" && chmod 600 "$f"' sh "$MYCNF"; then
  echo "🔴 寫 $MYCNF 失敗（2）：帳號已建、.env 已寫；請小芯處理"; exit 2
fi
unset P_APP P_BK P_LG
echo "  ✅ .env（640，備份 .env.bak-*）、.my.cnf（600）"

echo "== 4. www-data 權限（只動不是連結的檔，chgrp -h 不跟連結）"
[ ! -L "$ENVF" ] && [ -f "$ENVF" ] && chgrp -h www-data "$ENVF" || { echo "🔴 .env 不是一般檔或改群組失敗（2）"; exit 2; }
# chgrp -h 由 root 做（-h 只改連結本身，不跟過去）；chmod 會跟連結，所以由 ubuntu 自己改（檔案本來就是 ubuntu 的）
# 只有資料夾的 g+s 由 root 加（ubuntu 不在 www-data 群組時加不上）：就算被換成連結，影響也只是「新檔繼承群組」
find "$APP/storage" "$APP/bootstrap/cache" -xdev ! -type l -exec chgrp -h www-data {} + \
  && as_ubuntu find "$APP/storage" "$APP/bootstrap/cache" -xdev ! -type l -exec chmod g+rwX {} + \
  && find "$APP/storage" "$APP/bootstrap/cache" -xdev -type d -exec chmod g+s {} + \
  || { echo "🔴 權限沒改好（2）"; exit 2; }
n=$(find "$APP/storage" "$APP/bootstrap/cache" -xdev -type d \( ! -group www-data -o ! -perm -g+w \) | wc -l)
[ "$n" = 0 ] || { echo "🔴 還有 $n 個資料夾 www-data 寫不進去（2）"; exit 2; }
echo "  ✅"

echo "== 5. nginx 站台"
install -o root -g root -m 644 "$NC" "$SITE" && ln -s "$SITE" "$LINK" || { rm -f "$LINK" "$SITE"; echo "🔴 裝站台失敗（2）：資料庫部分已完成，nginx 沒動"; exit 2; }
if ! nginx -t >/dev/null 2>&1; then
  rm -f "$LINK" "$SITE"
  nginx -t >/dev/null 2>&1 && { echo "🔴 nginx -t 失敗，站台已拿掉（2）：資料庫部分已完成，請小芯看"; exit 2; }
  echo "🔴🔴 nginx -t 失敗而且拿掉站台後仍失敗（2）：請馬上看，不要 reload"; exit 2
fi
systemctl reload nginx || { echo "🔴 reload 失敗（2）"; exit 2; }
code=$(curl -sk -o /dev/null -w '%{http_code}' --resolve beta.loveuno.com:443:127.0.0.1 https://beta.loveuno.com/ || true)
echo "  ✅ 站台已裝、nginx 已 reload；本機打 https://beta.loveuno.com/ 回 $code（程式還沒 migrate 時 500 是正常的）"

echo "== 回讀"
for u in $USERS; do echo "  $u：$(sql "SHOW GRANTS FOR '$u'@'localhost'" | sed -E "s/IDENTIFIED BY PASSWORD '[^']*'/IDENTIFIED BY PASSWORD ***/" | tr '\n' ' ' | cut -c1-400)"; done
echo "完成（0）：接下來小芯用 ubuntu 跑 php artisan migrate、設 cron；Cloudflare 的 beta DNS 要另外加"
exit 0
