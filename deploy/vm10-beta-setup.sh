#!/bin/bash
# 新版愛諾園公測 beta.loveuno.com：vm10 一次性設定（要 root；熊叔叔跑）
# 熊叔叔 11/17：跑 vm10、網址 beta.loveuno.com、新建 loveuno2026 庫；11/18 選「乙 11/22 含回寫全部做完」；11/20 選「丙 照原訂 11/22 全部做完」
# Asana 1220564106252789（同步）、1220586286066431（停寫／備份）、1220456570088705（決策地圖）
#
# 做什麼（全部可回讀；任何一步失敗就回復已做的，不留半套）：
#   1. 從舊站 /home/loveuno/h2019/config.php 讀出兩個舊庫的「名字」（只讀名字，不印也不碰帳密），找出 basic、diary、relationship 在哪個庫
#   2. 建 loveuno2026 庫（utf8mb4）＋三個帳號（密碼隨機產生、只寫進檔案，不印出來）：
#        lu2026_app     新版程式用：loveuno2026 全權
#        lu2026_backup  每日備份用：loveuno2026 只讀＋鎖表（mysqldump 需要）
#        lu2026_legacy  同步與回寫用：兩個舊庫只讀＋ basic、diary、relationship 三張表的 INSERT、UPDATE（沒有 DELETE、沒有改結構）
#   3. 把帳密寫進 /home/ubuntu/loveuno-2026/.env（ubuntu:www-data 640）與 /home/ubuntu/.my.cnf 的 [mysqldump] 段（ubuntu 600）
#   4. storage/、bootstrap/cache 改成 www-data 寫得進去（php-fpm 是 www-data）
#   5. 裝 nginx 站台 beta.loveuno.com（設定檔先複製到 root 暫存、驗寫死的雜湊才裝）→ nginx -t → reload
# 用法：
#   sudo bash /tmp/vm10-beta-setup.sh            做
#   sudo bash /tmp/vm10-beta-setup.sh --undo     收回：拿掉 nginx 站台、刪三個帳號；**資料庫 loveuno2026 保留**（裡面是公測資料，要刪另外決定）
# 退出碼：0 完成；1 沒有做（或已完整回復），訊息寫卡在哪一步；2 🔴 回復失敗，要馬上看
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

if [ "$MODE" = "--undo" ]; then
  rc=0
  rm -f "$LINK" "$SITE"
  if [ -e "$LINK" ] || [ -e "$SITE" ]; then echo "🔴 nginx 站台檔刪不掉"; rc=2; fi
  if nginx -t >/dev/null 2>&1; then systemctl reload nginx && echo "nginx 站台已拿掉、已 reload"; else echo "🔴 拿掉站台後 nginx -t 失敗"; rc=2; fi
  for u in $USERS; do sql "DROP USER IF EXISTS '$u'@'localhost'" || rc=2; user_exists "$u" && { echo "🔴 帳號 $u 還在"; rc=2; }; done
  [ $rc = 0 ] && echo "已收回（0）：站台與三個帳號都拿掉了；資料庫 $DB 保留" || echo "🔴 收回沒有完全成功（2）"
  exit $rc
fi

echo "== 0. 檢查"
[ -f "$APP/artisan" ] && [ "$(stat -c %U "$APP")" = ubuntu ] || { echo "沒有做（1）：$APP 還沒部署（小芯先 git clone）"; exit 1; }
[ -f "$ENVF" ] || { echo "沒有做（1）：$ENVF 不存在（小芯先放好不含帳密的 .env）"; exit 1; }
[ -e "$SITE" ] || [ -e "$LINK" ] && { echo "沒有做（1）：beta 站台已經存在（已經跑過？）"; exit 1; }
for u in $USERS; do user_exists "$u" && { echo "沒有做（1）：帳號 $u 已經存在（已經跑過？）"; exit 1; }; done
grep -q '^\[mysqldump\]' "$MYCNF" 2>/dev/null && { echo "沒有做（1）：$MYCNF 已經有 [mysqldump] 段"; exit 1; }
nginx -t >/dev/null 2>&1 || { echo "沒有做（1）：現在的 nginx 設定就有錯，先不要動"; exit 1; }
sql "SELECT 1" >/dev/null || { echo "沒有做（1）：root 連不上 MariaDB"; exit 1; }
DB_EXISTED=0; [ "$(sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB'")" = 1 ] && DB_EXISTED=1
if [ $DB_EXISTED = 1 ] && [ "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB'")" != 0 ]; then
  echo "沒有做（1）：$DB 已經存在而且有資料表"; exit 1
fi

# nginx 設定：來源是 ubuntu 的檔 → 先複製到 root 暫存再驗雜湊（驗完不會被換掉）
NC=$(mktemp) || { echo "沒有做（1）：建不了暫存檔"; exit 1; }
SQLF=$(mktemp) || { rm -f "$NC"; echo "沒有做（1）：建不了暫存檔"; exit 1; }
cleanup() { rm -f "$NC" "$SQLF" "$ENVF.cora-new"; }
trap cleanup EXIT
cat "$CONF_SRC" > "$NC" 2>/dev/null && [ "$(sha256sum "$NC" | cut -d' ' -f1)" = "$NGINX_SHA" ] || { echo "沒有做（1）：nginx 設定檔不是審過的那一版（雜湊不符）"; exit 1; }

echo "== 1. 舊站資料庫（只讀名字）"
read -r OLD1 OLD2 < <(php -r 'error_reporting(0); ob_start(); include $argv[1]; ob_end_clean(); echo ($config["dbname"] ?? ""), " ", ($config["dbname2"] ?? ""), "\n";' "$OLDCFG")
for n in "${OLD1:-}" "${OLD2:-}"; do [[ "$n" =~ ^[A-Za-z0-9_]+$ ]] || { echo "沒有做（1）：讀不到舊站資料庫名（$OLDCFG）"; exit 1; }; done
echo "  舊庫：$OLD1、$OLD2"
declare -A LOC
for t in basic diary relationship; do
  s=$(sql "SELECT TABLE_SCHEMA FROM information_schema.TABLES WHERE TABLE_NAME='$t' AND TABLE_SCHEMA IN ('$OLD1','$OLD2')")
  [ "$(printf '%s\n' "$s" | grep -c .)" = 1 ] || { echo "沒有做（1）：$t 不在兩個舊庫其中剛好一個（找到：${s:-無}）"; exit 1; }
  LOC[$t]=$s; echo "  $t 在 $s"
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
GRANT INSERT, UPDATE ON \`${LOC[basic]}\`.\`basic\` TO 'lu2026_legacy'@'localhost';
GRANT INSERT, UPDATE ON \`${LOC[diary]}\`.\`diary\` TO 'lu2026_legacy'@'localhost';
GRANT INSERT, UPDATE ON \`${LOC[relationship]}\`.\`relationship\` TO 'lu2026_legacy'@'localhost';
EOF
undo_db() {
  local ok=0
  for u in $USERS; do sql "DROP USER IF EXISTS '$u'@'localhost'" || ok=1; user_exists "$u" && ok=1; done
  if [ $DB_EXISTED = 0 ]; then sql "DROP DATABASE IF EXISTS \`$DB\`" || ok=1; fi
  return $ok
}
if ! mariadb < "$SQLF" 2>/tmp/cora-beta-sql.err; then
  echo "  建庫或帳號失敗：$(sed -E 's/IDENTIFIED BY .*//' /tmp/cora-beta-sql.err | head -3)"; rm -f /tmp/cora-beta-sql.err
  undo_db && { echo "沒有做（1）：已回復（帳號刪除、新建的庫刪除）"; exit 1; }
  echo "🔴 回復失敗（2）：請看 mysql.user 裡 lu2026_*"; exit 2
fi
rm -f /tmp/cora-beta-sql.err "$SQLF"
echo "  ✅ 庫 $DB、三個帳號"

echo "== 3. 寫帳密（.env、.my.cnf）"
cp -p "$ENVF" "$ENVF.bak-$(date +%Y%m%d-%H%M%S)"
php -r '
$f = $argv[1]; $set = ["DB_CONNECTION"=>"mariadb","DB_HOST"=>"localhost","DB_PORT"=>"3306","DB_DATABASE"=>$argv[2],"DB_USERNAME"=>"lu2026_app","DB_PASSWORD"=>$argv[3],
  "LEGACY_DB_HOST"=>"localhost","LEGACY_DB_PORT"=>"3306","LEGACY_DB_DATABASE"=>$argv[4],"LEGACY_DB_DATABASE2"=>$argv[5],"LEGACY_DB_USERNAME"=>"lu2026_legacy","LEGACY_DB_PASSWORD"=>$argv[6]];
$lines = file($f, FILE_IGNORE_NEW_LINES); $seen = [];
foreach ($lines as &$l) { if (preg_match("/^([A-Z0-9_]+)=/", $l, $m) && isset($set[$m[1]])) { $l = $m[1] . "=" . $set[$m[1]]; $seen[$m[1]] = 1; } }
foreach ($set as $k => $v) if (!isset($seen[$k])) $lines[] = "$k=$v";
exit(file_put_contents($f . ".cora-new", implode("\n", $lines) . "\n") === false ? 1 : 0);' "$ENVF" "$DB" "$P_APP" "${LOC[basic]}" "$([ "${LOC[basic]}" = "$OLD1" ] && echo "$OLD2" || echo "$OLD1")" "$P_LG" \
  && chown ubuntu:www-data "$ENVF.cora-new" && chmod 640 "$ENVF.cora-new" && mv -f "$ENVF.cora-new" "$ENVF" \
  || { undo_db; echo "沒有做（1）：寫 .env 失敗，已回復帳號（.env 沒動）"; exit 1; }
{ [ -f "$MYCNF" ] && echo; printf '[mysqldump]\nuser=lu2026_backup\npassword=%s\n' "$P_BK"; } >> "$MYCNF" && chown ubuntu:ubuntu "$MYCNF" && chmod 600 "$MYCNF" \
  || { echo "🔴 寫 $MYCNF 失敗（2）：帳號已建、.env 已寫；請小芯處理"; exit 2; }
unset P_APP P_BK P_LG
echo "  ✅ .env（ubuntu:www-data 640，備份在 .env.bak-*）、.my.cnf（600）"

echo "== 4. storage／bootstrap/cache 給 www-data 寫"
chgrp -R www-data "$APP/storage" "$APP/bootstrap/cache" && chmod -R g+rwX "$APP/storage" "$APP/bootstrap/cache" \
  && find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod g+s {} + \
  || { echo "🔴 權限沒改好（2）"; exit 2; }
n=$(find "$APP/storage" "$APP/bootstrap/cache" -type d ! -perm -g+w | wc -l); [ "$n" = 0 ] || { echo "🔴 還有 $n 個資料夾 www-data 寫不進去（2）"; exit 2; }
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
for u in $USERS; do echo "  $u：$(sql "SHOW GRANTS FOR '$u'@'localhost'" | sed -E "s/IDENTIFIED BY PASSWORD '[^']*'/IDENTIFIED BY PASSWORD ***/" | tr '\n' ' ' | cut -c1-300)"; done
echo "完成（0）：接下來小芯用 ubuntu 跑 php artisan migrate、設 cron；Cloudflare 的 beta DNS 要另外加"
exit 0
