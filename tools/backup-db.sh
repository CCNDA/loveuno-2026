#!/bin/bash
# 新版愛諾園公測：資料庫每日備份（熊叔叔 11/17 TG 選甲；Asana 1220586286066431）
#   每天 03:00 由 ubuntu 的 crontab 跑：0 3 * * * /home/ubuntu/loveuno-2026/tools/backup-db.sh >> /home/ubuntu/backups/loveuno2026/backup.log 2>&1
#   帳密不放這裡：讀 ~/.my.cnf 的 [mysqldump] 段（只給 loveuno2026 的 SELECT、LOCK TABLES、SHOW VIEW、TRIGGER）
#   留 14 天；NAS 由 cora-lab 的 nas-pull 每天拉一份
# 退出碼：0 成功；1 失敗（cron 的 log 會留訊息）
set -uo pipefail
DB=${LU_BACKUP_DB:-loveuno2026}
DIR=${LU_BACKUP_DIR:-/home/ubuntu/backups/loveuno2026}
KEEP_DAYS=${LU_BACKUP_KEEP_DAYS:-14}
umask 077
mkdir -p "$DIR" || { echo "$(date '+%F %T') 🔴 建不了 $DIR"; exit 1; }
f="$DIR/$DB-$(date +%Y%m%d-%H%M%S).sql.gz"
tmp="$f.part"
if ! mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "$DB" | gzip -9 > "$tmp"; then
  rm -f "$tmp"; echo "$(date '+%F %T') 🔴 mysqldump 失敗，沒有留下半份"; exit 1
fi
# 驗收：gzip 完整、而且結尾有 mysqldump 的完成標記
if ! gzip -t "$tmp" || ! zcat "$tmp" | tail -1 | grep -q '^-- Dump completed'; then
  rm -f "$tmp"; echo "$(date '+%F %T') 🔴 備份檔不完整，已刪"; exit 1
fi
mv "$tmp" "$f"
n=$(find "$DIR" -name "$DB-*.sql.gz" -mtime +"$KEEP_DAYS" -print -delete | wc -l)
echo "$(date '+%F %T') ✅ $(basename "$f") $(du -h "$f" | cut -f1)；刪掉超過 $KEEP_DAYS 天的 $n 份；目前 $(find "$DIR" -name "$DB-*.sql.gz" | wc -l) 份"
