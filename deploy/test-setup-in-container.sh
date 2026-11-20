#!/bin/bash
# vm10-beta-setup.sh 的拋棄式容器實測（root）。跑法：sudo docker run --rm -v <repo>:/w:ro ubuntu:24.04 bash /w/deploy/test-setup-in-container.sh
# 退出碼：0 全過；1 有失敗；3 環境裝不起來
set -u
fail=0; n=0
ok()  { n=$((n+1)); if [ "$1" = 0 ]; then echo "✅ $2"; else echo "🔴 $2"; fail=$((fail+1)); fi; }
die() { echo "🛑 環境壞了：$*"; exit 3; }
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq >/dev/null 2>&1 && apt-get install -y -qq mariadb-server nginx php-cli openssl curl >/dev/null 2>&1 || die "apt 裝不起來"
id ubuntu >/dev/null 2>&1 || useradd -m ubuntu
id www-data >/dev/null 2>&1 || die "沒有 www-data"
(mysqld_safe >/dev/null 2>&1 &) ; for i in $(seq 30); do mariadb -e 'select 1' >/dev/null 2>&1 && break; sleep 1; done
mariadb -e 'select 1' >/dev/null 2>&1 || die "MariaDB 起不來"
# systemctl 替身：reload nginx → nginx -s reload（容器沒有 systemd）
printf '#!/bin/sh\n[ "$1 $2" = "reload nginx" ] && { nginx -s reload 2>/dev/null || nginx; exit 0; }\nexit 1\n' > /usr/bin/systemctl; chmod 755 /usr/bin/systemctl
nginx || die "nginx 起不來"

S=/tmp/vm10-beta-setup.sh; cp /w/deploy/vm10-beta-setup.sh $S
APP=/home/ubuntu/loveuno-2026
fresh() {   # 每個情境從乾淨的狀態開始
  bash $S --undo >/dev/null 2>&1
  mariadb -e "DROP DATABASE IF EXISTS loveuno2026; DROP DATABASE IF EXISTS austwoon_unomember; DROP DATABASE IF EXISTS austwoon_unocontent;"
  mariadb -e "CREATE DATABASE austwoon_unomember; CREATE DATABASE austwoon_unocontent;
    CREATE TABLE austwoon_unomember.basic (id INT PRIMARY KEY, email VARCHAR(50), password VARCHAR(50), nickname VARCHAR(20), status INT, modify_date TIMESTAMP NULL) CHARSET utf8mb3;
    CREATE TABLE austwoon_unomember.profile (user_id INT PRIMARY KEY, intro TEXT) CHARSET utf8mb3;
    CREATE TABLE austwoon_unomember.relationship (a INT, b INT) CHARSET utf8mb3;
    CREATE TABLE austwoon_unocontent.diary (id INT PRIMARY KEY, title VARCHAR(50)) CHARSET utf8mb3;
    CREATE TABLE austwoon_unomember.coin (id INT) CHARSET utf8mb3;"
  rm -rf $APP /home/loveuno /home/ubuntu/ssl /home/ubuntu/.my.cnf
  mkdir -p $APP/storage/logs $APP/bootstrap/cache $APP/public $APP/deploy/nginx /home/loveuno/h2019 /home/ubuntu/ssl
  touch $APP/artisan; echo '<?php echo "ok";' > $APP/public/index.php
  printf 'APP_NAME=Loveuno\nDB_CONNECTION=sqlite\nDB_PASSWORD=\n' > $APP/.env
  install -m 644 /w/deploy/nginx/beta.loveuno.com.conf $APP/deploy/nginx/beta.loveuno.com.conf
  chown -R ubuntu:ubuntu /home/ubuntu
  printf '<?php\n$config = ["dbhost"=>"localhost","dbuser"=>"x","dbpassword"=>"SECRET-NOT-PRINTED","dbname"=>"austwoon_unomember","dbname2"=>"austwoon_unocontent"];\n' > /home/loveuno/h2019/config.php
  openssl req -x509 -newkey rsa:2048 -nodes -subj /CN=loveuno.com -keyout /home/ubuntu/ssl/loveuno.com.key -out /home/ubuntu/ssl/loveuno.com.crt -days 1 >/dev/null 2>&1
  [ -f /etc/nginx/snippets/cloudflare-realip.conf ] || echo '# 替身' > /etc/nginx/snippets/cloudflare-realip.conf
}
gr() { mariadb -N -e "SHOW GRANTS FOR '$1'@'localhost'" 2>/dev/null | tr '\n' ' '; }

echo "== S1 成功"
fresh; out=$(bash $S 2>&1); rc=$?
[ $rc = 0 ]; ok $? "S1 退出 0（$(echo "$out" | tail -1)）"
echo "$out" | grep -q 'SECRET-NOT-PRINTED'; [ $? != 0 ]; ok $? "S1 輸出沒有舊站密碼"
p=$(grep '^DB_PASSWORD=' $APP/.env | cut -d= -f2); [ ${#p} = 32 ]; ok $? "S1 .env 有 32 碼密碼"
echo "$out" | grep -qF "$p"; [ $? != 0 ]; ok $? "S1 輸出沒有新密碼"
[ "$(stat -c '%U:%G %a' $APP/.env)" = "ubuntu:www-data 640" ]; ok $? "S1 .env ubuntu:www-data 640"
grep -q '^DB_DATABASE=loveuno2026$' $APP/.env && grep -q '^LEGACY_DB_DATABASE=austwoon_unomember$' $APP/.env && grep -q '^LEGACY_DB_DATABASE2=austwoon_unocontent$' $APP/.env && grep -q '^APP_NAME=Loveuno$' $APP/.env; ok $? "S1 .env 鍵值對、原本的鍵還在"
[ "$(grep -c '^DB_PASSWORD=' $APP/.env)" = 1 ]; ok $? "S1 DB_PASSWORD 只有一行（取代不是附加）"
ls $APP/.env.bak-* >/dev/null 2>&1; ok $? "S1 留了 .env 備份"
[ "$(stat -c '%U %a' /home/ubuntu/.my.cnf)" = "ubuntu 600" ] && grep -q '^user=lu2026_backup$' /home/ubuntu/.my.cnf; ok $? "S1 .my.cnf ubuntu 600、[mysqldump] lu2026_backup"
mariadb -u lu2026_app -p"$p" -e "CREATE TABLE loveuno2026.t (i INT); DROP TABLE loveuno2026.t" 2>/dev/null; ok $? "S1 lu2026_app 能在 loveuno2026 建表"
lp=$(grep '^LEGACY_DB_PASSWORD=' $APP/.env | cut -d= -f2)
mariadb -u lu2026_legacy -p"$lp" -e "SELECT COUNT(*) FROM austwoon_unomember.coin; SELECT COUNT(*) FROM austwoon_unocontent.diary" >/dev/null 2>&1; ok $? "S1 lu2026_legacy 兩個舊庫都能讀"
mariadb -e "INSERT INTO austwoon_unomember.basic (id,email,password,nickname,status) VALUES (1,'a@x','pw','a',2); INSERT INTO austwoon_unomember.profile VALUES (1,'hi')"
mariadb -u lu2026_legacy -p"$lp" -e "UPDATE austwoon_unomember.basic SET nickname='b', modify_date=NOW() WHERE id=1; UPDATE austwoon_unomember.profile SET intro='新' WHERE user_id=1; INSERT INTO austwoon_unocontent.diary VALUES (1,'t'); UPDATE austwoon_unocontent.diary SET title='u'; INSERT INTO austwoon_unomember.relationship VALUES (1,2); UPDATE austwoon_unomember.relationship SET b=3; DELETE FROM austwoon_unomember.relationship WHERE a=1" 2>/dev/null; ok $? "S1 lu2026_legacy 回寫需要的都能做（basic 兩欄、profile、diary、relationship 含刪除）"
for q in "UPDATE austwoon_unomember.basic SET email='evil@x'" "UPDATE austwoon_unomember.basic SET password='x'" "UPDATE austwoon_unomember.basic SET status=9" "INSERT INTO austwoon_unomember.basic (id) VALUES (9)" "DELETE FROM austwoon_unomember.basic" "INSERT INTO austwoon_unomember.profile VALUES (2,'x')" "DELETE FROM austwoon_unomember.profile" "DELETE FROM austwoon_unocontent.diary" "INSERT INTO austwoon_unomember.coin VALUES (1)" "DROP TABLE austwoon_unocontent.diary" "ALTER TABLE austwoon_unomember.basic ADD x INT" "CREATE TABLE austwoon_unomember.z (i INT)"; do
  mariadb -u lu2026_legacy -p"$lp" -e "$q" 2>/dev/null; [ $? != 0 ]; ok $? "S1 lu2026_legacy 不能：$q"
done
mariadb -u lu2026_app -p"$p" -e "SELECT 1 FROM austwoon_unomember.basic LIMIT 1" 2>/dev/null; [ $? != 0 ]; ok $? "S1 lu2026_app 讀不到舊庫"
bk=$(grep '^password=' /home/ubuntu/.my.cnf | cut -d= -f2)
mariadb -u lu2026_backup -p"$bk" -e "CREATE TABLE loveuno2026.t (i INT)" 2>/dev/null; [ $? != 0 ]; ok $? "S1 lu2026_backup 不能寫"
[ -L /etc/nginx/sites-enabled/beta.loveuno.com ] && cmp -s /etc/nginx/sites-available/beta.loveuno.com /w/deploy/nginx/beta.loveuno.com.conf && nginx -t >/dev/null 2>&1; ok $? "S1 nginx 站台已裝、內容一樣、nginx -t 通過"
[ "$(find $APP/storage $APP/bootstrap/cache ! -group www-data | wc -l)" = 0 ]; ok $? "S1 storage／cache 群組 www-data"
code=$(curl -sk -o /dev/null -w '%{http_code}' --resolve beta.loveuno.com:443:127.0.0.1 https://beta.loveuno.com/.env); [ "$code" = 404 ]; ok $? "S1 /.env 回 404（$code）"

echo "== S2 重跑"
h=$(sha256sum $APP/.env); out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ "$(sha256sum $APP/.env)" = "$h" ]; ok $? "S2 已經跑過 → 退出 1、.env 沒動（$(echo "$out" | tail -1)）"

echo "== S3 --undo"
out=$(bash $S --undo 2>&1); rc=$?
[ $rc = 0 ] && [ ! -e /etc/nginx/sites-enabled/beta.loveuno.com ] && [ -z "$(gr lu2026_app)$(gr lu2026_legacy)$(gr lu2026_backup)" ]; ok $? "S3 退出 0、站台和三個帳號都拿掉（$(echo "$out" | tail -1)）"
[ "$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='loveuno2026'")" = 1 ]; ok $? "S3 資料庫 loveuno2026 保留"
nginx -t >/dev/null 2>&1; ok $? "S3 nginx -t 通過"

echo "== S4 nginx 設定被 ubuntu 換掉"
fresh; runuser -u ubuntu -- sh -c "echo 'server { listen 80; location / { proxy_pass http://evil; } }' > $APP/deploy/nginx/beta.loveuno.com.conf"
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ] && [ ! -e /etc/nginx/sites-available/beta.loveuno.com ] && echo "$out" | grep -q 雜湊不符; ok $? "S4 退出 1、雜湊不符、什麼都沒建（$(echo "$out" | tail -1)）"

echo "== S5 diary 兩個庫都有（找不到唯一位置）"
fresh; mariadb -e "CREATE TABLE austwoon_unomember.diary (id INT) CHARSET utf8mb3"
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ] && grep -q '^DB_CONNECTION=sqlite$' $APP/.env; ok $? "S5 退出 1、沒建帳號、.env 沒動（$(echo "$out" | tail -1)）"

echo "== S6 .my.cnf 已經有 [mysqldump]"
fresh; printf '[mysqldump]\nuser=someone\n' > /home/ubuntu/.my.cnf; chown ubuntu /home/ubuntu/.my.cnf
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ]; ok $? "S6 退出 1、沒建帳號（$(echo "$out" | tail -1)）"

echo "== S7 舊庫少一張表（relationship）"
fresh; mariadb -e "DROP TABLE austwoon_unomember.relationship"
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ]; ok $? "S7 退出 1、沒留下帳號（$(echo "$out" | tail -1)）"

echo "== S8 舊站設定讀不到庫名"
fresh; printf '<?php\n$config = ["dbname"=>"bad name; drop"];\n' > /home/loveuno/h2019/config.php
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ]; ok $? "S8 庫名不合規 → 退出 1、沒建帳號（$(echo "$out" | tail -1)）"

echo "== S9 建帳號的 SQL 跑到最後一句才失敗 → 前面建的帳號和新庫都要回復"
fresh
mv /usr/bin/mariadb /usr/bin/mariadb.real
cat > /usr/bin/mariadb <<'EOF2'
#!/bin/bash
# 包裝：從 stdin 讀 SQL 檔的那一次（建庫建帳號），在最後加一句一定失敗的 GRANT
case " $* " in *" -e "*) ;; *) { cat; echo "GRANT SELECT ON no_such_db_cora.no_such_table TO 'lu2026_app'@'localhost';"; } | /usr/bin/mariadb.real "$@"; exit $?;; esac
exec /usr/bin/mariadb.real "$@"
EOF2
chmod 755 /usr/bin/mariadb
out=$(bash $S 2>&1); rc=$?
mv -f /usr/bin/mariadb.real /usr/bin/mariadb
[ $rc = 1 ] && [ -z "$(gr lu2026_app)$(gr lu2026_backup)$(gr lu2026_legacy)" ] && [ "$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='loveuno2026'")" = 0 ] && grep -q '^DB_CONNECTION=sqlite$' $APP/.env; ok $? "S9 退出 1、三個帳號和新庫都回復、.env 沒動（$(echo "$out" | tail -1)）"
echo "$out" | grep -qE "IDENTIFIED BY '|[A-Za-z0-9]{32}"; [ $? != 0 ]; ok $? "S9 錯誤訊息沒有帶出密碼"


echo "== S10 .env 事先被換成連到 root 專屬檔的連結（小布 🔴1）"
fresh; echo 'ROOT-ONLY-SECRET-10' > /root/secret10; chmod 600 /root/secret10
rm -f $APP/.env; runuser -u ubuntu -- ln -s /root/secret10 $APP/.env
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ] && echo "$out" | grep -q "符號連結"; ok $? "S10 退出 1、沒建帳號（$(echo "$out" | tail -1)）"
grep -rl 'ROOT-ONLY-SECRET-10' /home/ubuntu 2>/dev/null | grep -q .; [ $? != 0 ]; ok $? "S10 root 檔的內容沒有被複製到 ubuntu 家目錄"
[ "$(stat -c '%U:%G %a' /root/secret10)" = "root:root 600" ] && grep -q '^ROOT-ONLY-SECRET-10$' /root/secret10; ok $? "S10 root 檔擁有者、權限、內容都沒變"

echo "== S11 .my.cnf 事先被換成連到 /etc/sudoers.d 的連結（小布 🔴1）"
fresh; runuser -u ubuntu -- ln -s /etc/sudoers.d/cora-evil /home/ubuntu/.my.cnf
out=$(bash $S 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ]; ok $? "S11 退出 1、沒建帳號（$(echo "$out" | tail -1)）"
[ ! -e /etc/sudoers.d/cora-evil ]; ok $? "S11 /etc/sudoers.d/cora-evil 沒有被建出來"

echo "== S12 storage 裡事先放了連到 /etc/passwd 的連結（小布 🔴1：chgrp／chmod 不能跟過去）"
fresh; before=$(stat -c '%U:%G %a' /etc/passwd); runuser -u ubuntu -- ln -s /etc/passwd $APP/storage/logs/evil
out=$(bash $S 2>&1); rc=$?
[ $rc = 0 ]; ok $? "S12 照常完成（$(echo "$out" | tail -1)）"
[ "$(stat -c '%U:%G %a' /etc/passwd)" = "$before" ]; ok $? "S12 /etc/passwd 擁有者、群組、權限都沒變（$before）"

echo "== S13 舊站 config.php 裡有程式碼（小布 🔴2：不能執行）"
fresh; printf '<?php\nfile_put_contents("/tmp/pwned13", "x");\n$config = ["dbname"=>"austwoon_unomember","dbname2"=>"austwoon_unocontent"];\n' > /home/loveuno/h2019/config.php; rm -f /tmp/pwned13
out=$(bash $S 2>&1); rc=$?
[ $rc = 0 ]; ok $? "S13 照常完成（只讀字面值）"
[ ! -e /tmp/pwned13 ]; ok $? "S13 config.php 沒有被執行（/tmp/pwned13 不存在）"

echo "== S14 密碼不放在任何指令的參數（小布 🟠3：靜態檢查）"
bad=$(grep -nE '\$P_(APP|BK|LG)' $S | grep -vE '^[0-9]+:(P_APP=|\[ \$\{#P_APP\}|CREATE USER|unset P_APP|     "\$DB" "\$P_APP")' )
# 允許的只有：產生、檢查長度、SQL 檔（heredoc）、unset，以及 printf 的參數（printf 是 shell 內建，不會出現在程序命令列）
[ -z "$(echo "$bad" | grep -vE 'printf|"\$P_BK" \| as_ubuntu')" ]; ok $? "S14 用到密碼變數的地方都是內建或 heredoc（$(echo "$bad" | wc -l) 處）"
grep -n '/tmp/cora-' $S | grep -q .; [ $? != 0 ]; ok $? "S14 沒有固定檔名的 /tmp 暫存（小布 🟠4）"

echo "== S15 硬連結：ubuntu 不能在 storage 裡硬連結到 root 的檔（靠 fs.protected_hardlinks）"
fresh; runuser -u ubuntu -- ln /etc/shadow $APP/storage/logs/hard 2>/dev/null; [ ! -e $APP/storage/logs/hard ]; ok $? "S15 ubuntu 建不出指到 /etc/shadow 的硬連結（protected_hardlinks=$(sysctl -n fs.protected_hardlinks)）"
before=$(stat -c '%G' /etc/shadow); out=$(bash $S 2>&1); [ "$(stat -c '%G' /etc/shadow)" = "$before" ]; ok $? "S15 跑完 /etc/shadow 群組沒變（$before）"

echo "== S16 主機沒開連結保護就停（用替身 sysctl 模擬）"
fresh; mv /usr/sbin/sysctl /usr/sbin/sysctl.real; printf '#!/bin/sh
echo 0
' > /usr/sbin/sysctl; chmod 755 /usr/sbin/sysctl
out=$(bash $S 2>&1); rc=$?; mv -f /usr/sbin/sysctl.real /usr/sbin/sysctl
[ $rc = 1 ] && [ -z "$(gr lu2026_app)" ] && echo "$out" | grep -q protected_hardlinks; ok $? "S16 退出 1、沒建帳號（$(echo "$out" | tail -1)）"

echo "== S17 MariaDB root 要密碼（vm10 實況，熊叔叔 11/20 20:4x）：socket → debian.cnf → 終端機輸入密碼"
command -v script >/dev/null || die "沒有 script（util-linux）"
fresh
mariadb -e "ALTER USER root@localhost IDENTIFIED VIA mysql_native_password USING PASSWORD('cora-test-pw-17'); FLUSH PRIVILEGES;" || die "改不了 root 驗證方式"
M="mariadb -uroot -pcora-test-pw-17"
grm() { $M -N -e "SHOW GRANTS FOR '$1'@'localhost'" 2>/dev/null | tr '\n' ' '; }
undo17() { $M -e "DROP USER IF EXISTS 'lu2026_app'@'localhost'; DROP USER IF EXISTS 'lu2026_backup'@'localhost'; DROP USER IF EXISTS 'lu2026_legacy'@'localhost'; DROP DATABASE IF EXISTS loveuno2026;" 2>/dev/null; rm -f /etc/nginx/sites-enabled/beta.loveuno.com /etc/nginx/sites-available/beta.loveuno.com /home/ubuntu/.my.cnf; }
reset17() { undo17; rm -f /etc/mysql/debian.cnf; rm -rf $APP; mkdir -p $APP/storage/logs $APP/bootstrap/cache $APP/public $APP/deploy/nginx; touch $APP/artisan; printf 'APP_NAME=Loveuno\nDB_CONNECTION=sqlite\n' > $APP/.env; install -m 644 /w/deploy/nginx/beta.loveuno.com.conf $APP/deploy/nginx/beta.loveuno.com.conf; chown -R ubuntu:ubuntu /home/ubuntu; }
mariadb --sandbox -e "SELECT 1" >/dev/null 2>&1; [ $? != 0 ]; ok $? "S17 前提：root 走 socket 已經連不上"
$M --sandbox -e 'system echo SANDBOX-LEAK' 2>&1 | grep -q SANDBOX-LEAK; [ $? != 0 ]; ok $? "S17 --sandbox 擋住 system 指令"

reset17; printf '[client]\nuser=root\npassword=cora-test-pw-17\n' > /etc/mysql/debian.cnf; chmod 600 /etc/mysql/debian.cnf
out=$(bash $S < /dev/null 2>&1); rc=$?
[ $rc = 0 ] && echo "$out" | grep -q '用 /etc/mysql/debian.cnf' && [ -n "$(grm lu2026_app)" ]; ok $? "S17a socket 不行、debian.cnf 可以 → 用它完成（$(echo "$out" | grep MariaDB：)）"

reset17; printf '[client]\nuser=root\npassword=cora-test-pw-17\n' > /etc/mysql/debian.cnf; chmod 644 /etc/mysql/debian.cnf
out=$(bash $S < /dev/null 2>&1); rc=$?
[ $rc = 1 ] && [ -z "$(grm lu2026_app)" ]; ok $? "S17b debian.cnf 不是 root 600 → 不用它、不是終端機也不問密碼 → 退出 1、沒建帳號"

reset17; before=$(ls /tmp | sort)
out=$( (sleep 3; printf 'cora-test-pw-17\n') | script -qec "bash $S" /dev/null 2>&1); rc=$?   # 等提示出現才打字（跟真人一樣）
echo "$out" | grep -q '用輸入的 root 密碼' && echo "$out" | grep -q '完成（0）' && [ -n "$(grm lu2026_app)" ]; ok $? "S17c 終端機輸入正確密碼 → 完成"
echo "$out" | grep -q 'cora-test-pw-17'; [ $? != 0 ]; ok $? "S17c 輸出裡沒有密碼（輸入時不顯示）"
grep -rl 'cora-test-pw-17' /tmp 2>/dev/null | grep -q .; [ $? != 0 ]; ok $? "S17c /tmp 沒有留下含密碼的檔"

reset17
out=$( (sleep 3; printf 'wrong-pw\n') | script -qec "bash $S" /dev/null 2>&1); rc=$?   # 等提示出現才打字（跟真人一樣）
echo "$out" | grep -q '密碼不對或連不上' && echo "$out" | grep -q '沒有做（1）' && [ -z "$(grm lu2026_app)" ]; ok $? "S17d 密碼錯 → 退出 1、沒建帳號"
grep -rl 'wrong-pw' /tmp 2>/dev/null | grep -q .; [ $? != 0 ]; ok $? "S17d /tmp 沒有留下密碼檔"

reset17; printf '[client]\nuser=root\npassword=cora-test-pw-17\n' > /etc/mysql/debian.cnf; chmod 600 /etc/mysql/debian.cnf
bash $S < /dev/null >/dev/null 2>&1
out=$(bash $S --undo < /dev/null 2>&1); rc=$?
[ $rc = 0 ] && [ -z "$(grm lu2026_app)$(grm lu2026_legacy)$(grm lu2026_backup)" ]; ok $? "S17e --undo 也走同一套連線（debian.cnf）收回帳號"

echo; echo "共 $n 項，失敗 $fail 項"
if [ $fail = 0 ]; then echo "全部通過"; exit 0; else echo "有失敗"; exit 1; fi
