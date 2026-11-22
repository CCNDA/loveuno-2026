<?php
// 在 vm10（舊站正式機）上執行：匯出會員的內容（第二階段），給新版愛諾園 legacy:import-content 匯入。
// 熊叔叔 2026-10-30 TG「3都搬」。對照表在 docs/legacy-migration.md。Asana 1215470856990770。
//
// 用法（ubuntu 身分）：
//   php legacy_export_content.php --cnf=<cora_ro 的設定檔，host=127.0.0.1> --out=/tmp/lu2026_content --kind=diary,pray,friend,guestbook,album,date,testi [--images]
//
// 每一種寫一個 JSONL（一行一筆）：diary／diary_reply／pray／pray_reply／friend／guestbook／album／date／testi.jsonl
// - 文字照舊站存法還原：emoji_decode（用舊站自己的 h2019/emoji_lib.php）、<br> → 換行、&lt; &gt; 等還原、‵ → '
//   新版畫面一律 escape，所以還原成純文字是安全的
// - status -1（後台隱藏）的也匯出，帶 status，匯入端決定怎麼處理
// - --images：日記照片從本機 diary_pic/ 或 R2 讀出來放進 images/，匯入端重新轉檔
// - --images-after=album:31000,diary:50000,testi:15：只抓編號比這個大的照片檔（資料列照樣全部匯出）。
//   照片「切換前先搬」：前幾天先整批匯出轉檔，切換當天只補之後新增的（匯入端已經有的會跳過）
// - --images-upto=album:5000：只抓編號 ≤ 這個的照片，跟 --images-after 配成區間分批跑（11/22：相簿約 2 萬張×1 MB，一次會塞爆硬碟）
//   ⚠️ 只有相簿適合分批：相簿沒有照片的那筆匯入端會跳過、下一批還能補；日記沒有照片也會先建起來，之後不會補照片 → 日記照片一次匯
// - --min-free-gb=3：寫照片前剩餘空間低於這個就停（exit 3）
// - --since='YYYY-MM-DD HH:MM:SS'：公測增量（Asana 1220564106252789）。日記挑「作者 basic.modify_date 在這之後」的
//   （舊站寫／改／刪日記都會更新作者的 modify_date，日記本身只有日期）；回覆、代禱、留言板、約會、見證用各自的時間欄。
//   好友、相簿沒有時間欄，不能用 --since（每天全量對帳）
// - 只用唯讀帳號；輸出目錄 700、檔案 600，用完請刪掉

declare(strict_types=1);

$opts = getopt('', ['cnf:', 'out:', 'kind:', 'images', 'images-after::', 'images-upto::', 'web::', 'r2::', 'db::', 'since::', 'min-free-gb::']);
$kinds = array_filter(explode(',', (string) ($opts['kind'] ?? '')));
$known = ['diary', 'pray', 'friend', 'guestbook', 'album', 'date', 'testi'];
if (empty($opts['cnf']) || empty($opts['out']) || ! $kinds || array_diff($kinds, $known)) {
    fwrite(STDERR, "用法：php legacy_export_content.php --cnf=<唯讀設定檔> --out=<輸出目錄> --kind=" . implode(',', $known) . " [--images]\n");
    exit(2);
}
$since = isset($opts['since']) && $opts['since'] !== false ? (string) $opts['since'] : null;
if ($since !== null && ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    fwrite(STDERR, "--since 要是 YYYY-MM-DD HH:MM:SS（舊站時間）\n");
    exit(2);
}
if ($since !== null && array_intersect($kinds, ['friend', 'album'])) {
    fwrite(STDERR, "好友、相簿沒有時間欄，不能用 --since（用全量對帳）\n");
    exit(2);
}
$db = preg_match('/^[A-Za-z0-9_]+$/', (string) ($opts['db'] ?? '')) ? $opts['db'] : 'austwoon_unomember'; // 只有測試時才指定別的庫
$web = rtrim($opts['web'] ?? '/home/loveuno', '/');
$r2 = rtrim($opts['r2'] ?? '', '/');
$withImages = isset($opts['images']);
$imagesAfter = $imagesUpto = [];
foreach (['images-after' => 'imagesAfter', 'images-upto' => 'imagesUpto'] as $o => $var) {
    if (isset($opts[$o]) && $opts[$o] !== false && $opts[$o] !== '') {
        foreach (explode(',', (string) $opts[$o]) as $pair) {
            if (! preg_match('/^(diary|album|testi):(\d+)$/', trim($pair), $m)) {
                fwrite(STDERR, "--{$o} 要寫成 album:31000,diary:50000（種類：diary、album、testi）\n");
                exit(2);
            }
            $$var[$m[1]] = (int) $m[2];
        }
    }
}
$minFreeGb = isset($opts['min-free-gb']) && is_numeric($opts['min-free-gb']) ? (float) $opts['min-free-gb'] : 3.0;

if (is_file("{$web}/h2019/emoji_lib.php")) {
    require_once "{$web}/h2019/emoji_lib.php"; // 舊站自己的 emoji_decode，照它存的方式解回來
} else {
    fwrite(STDERR, "⚠️ 找不到 {$web}/h2019/emoji_lib.php：emoji 會留著 \\uXXXX 沒有還原（--web 指到舊站根目錄）\n");
}

$cnf = parse_ini_file($opts['cnf'], true, INI_SCANNER_RAW);
$c = $cnf['client'] ?? [];
if (empty($c['user']) || ! isset($c['password'])) {
    fwrite(STDERR, "唯讀設定檔讀不到 user／password\n");
    exit(2);
}
$host = preg_match('/^[A-Za-z0-9.\-]+$/', (string) ($c['host'] ?? '')) ? $c['host'] : 'localhost';
$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
unset($c, $cnf);
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); // 5 萬筆一筆一筆讀，不要整包放進記憶體

$out = rtrim($opts['out'], '/');
if (! is_dir($out) && ! mkdir($out, 0700, true)) {
    fwrite(STDERR, "建不了輸出目錄\n");
    exit(1);
}
chmod($out, 0700);
if ($withImages) {
    if (! is_dir("{$out}/images") && ! mkdir("{$out}/images", 0700)) {
        fwrite(STDERR, "建不了 images 目錄\n");
        exit(1);
    }
    chmod("{$out}/images", 0700);
}

// 舊站存法 → 純文字
function legacy_text(?string $s): string
{
    $s = (string) $s;
    if (function_exists('emoji_decode')) {
        $s = emoji_decode($s);
    }
    $s = preg_replace('#<br\s*/?>#i', "\n", $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace('‵', "'", $s);
    $s = str_replace("\r", '', $s);

    return trim($s);
}

// 從本機或 R2 讀一個舊站檔案，是圖片才存進 images/<name>.img；回傳相對路徑或 null
function legacy_image(string $sub, string $file, bool $isR2, string $name): ?string
{
    global $withImages, $imagesAfter, $imagesUpto, $web, $r2, $out, $minFreeGb;
    $file = ltrim($file, '/');
    if (! $withImages || $file === '' || str_contains($file, '..')) {
        return null;
    }
    // $name 是「種類_編號」：--images-after 給了那一種的門檻，就只抓編號比門檻大的
    if (preg_match('/^([a-z]+)_(\d+)$/', $name, $m) && isset($imagesAfter[$m[1]]) && (int) $m[2] <= $imagesAfter[$m[1]]) {
        return null;
    }
    if (preg_match('/^([a-z]+)_(\d+)$/', $name, $m) && isset($imagesUpto[$m[1]]) && (int) $m[2] > $imagesUpto[$m[1]]) {
        return null;
    }
    $bytes = ($isR2 && $r2 !== '')
        ? @file_get_contents("{$r2}/{$sub}/" . str_replace('%2F', '/', rawurlencode($file)))
        : @file_get_contents("{$web}/{$sub}/{$file}");
    if ($bytes === false || ! @getimagesizefromstring($bytes)) {
        return null;
    }
    $rel = "images/{$name}.img";
    ensure_free_space($out, $minFreeGb);
    file_put_contents("{$out}/{$rel}", $bytes);
    chmod("{$out}/{$rel}", 0600);

    return $rel;
}

function legacy_time(?string $t): ?string
{
    return ($t === null || $t === '' || str_starts_with($t, '0000')) ? null : $t;
}

$counts = [];
// 有 --since 時加上「這種資料自己的變動條件」（每個 ? 都代入 since）；沒有就整批
$q = function (string $select, ?string $changed, string $order) use ($pdo, $since) {
    if ($since === null || $changed === null) {
        return $pdo->query($select . $order);
    }
    $st = $pdo->prepare($select . (str_contains($select, ' WHERE ') ? ' AND ' : ' WHERE ') . "({$changed})" . $order);
    $st->execute(array_fill(0, substr_count($changed, '?'), $since));

    return $st;
};

$write = function (string $name, iterable $rows, callable $map) use ($out, &$counts): void {
    $f = fopen("{$out}/{$name}.jsonl", 'w');
    chmod("{$out}/{$name}.jsonl", 0600);
    $n = 0;
    foreach ($rows as $r) {
        $line = $map($r);
        if ($line !== null) {
            fwrite($f, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
            $n++;
        }
    }
    fclose($f);
    $counts[$name] = $n;
};

$diaryOwners = null; // --since 時：這次涵蓋的作者（他們在舊站的日記這次是「全部」，匯入端用來找舊站刪掉的）
if (in_array('diary', $kinds, true)) {
    $imgCount = 0;
    if ($since !== null) {
        // 先固定名單、再用同一份名單挑日記：中間有人剛改，不會變成「在名單上但日記沒匯到」
        $st = $pdo->prepare('SELECT id FROM basic WHERE modify_date >= ? ORDER BY id');
        $st->execute([$since]);
        $diaryOwners = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $st->closeCursor();
    }
    $diaryRows = $diaryOwners === null
        ? $pdo->query('SELECT id, ownerid, subject, content, date, status, pic, is_r2 FROM diary ORDER BY id')
        : ($diaryOwners === [] ? [] : (function () use ($pdo, $diaryOwners) {
            $st = $pdo->prepare('SELECT id, ownerid, subject, content, date, status, pic, is_r2 FROM diary WHERE ownerid IN (' . implode(',', array_fill(0, count($diaryOwners), '?')) . ') ORDER BY id');
            $st->execute($diaryOwners);

            return $st;
        })());
    $write('diary', $diaryRows,
        function (array $r) use (&$imgCount): array {
            $pic = ltrim((string) $r['pic'], '/');
            $image = legacy_image('diary_pic', $pic, (int) $r['is_r2'] === 1, "diary_{$r['id']}");
            $imgCount += $image ? 1 : 0;

            return [
                'legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'],
                'title' => legacy_text($r['subject']), 'body' => legacy_text($r['content']),
                'date' => legacy_time($r['date']), 'status' => (int) $r['status'],
                'has_pic' => $pic !== '', 'image' => $image,
            ];
        });
    $counts['diary_images'] = $imgCount;
    $write('diary_reply', $q('SELECT id, diaryid, ownerid, status, msg, timestamp FROM diary_reply', 'timestamp >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'parent' => (int) $r['diaryid'], 'owner' => (int) $r['ownerid'],
            'status' => (int) $r['status'], 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
}

if (in_array('pray', $kinds, true)) {
    $write('pray', $q('SELECT id, ownerid, status, subject, msg, timestamp FROM pray', 'timestamp >= ? OR last_reply_time >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'], 'status' => (int) $r['status'],
            'title' => legacy_text($r['subject']), 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
    $write('pray_reply', $q('SELECT id, ownerid, prayid, status, msg, timestamp FROM pray_reply', 'timestamp >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'parent' => (int) $r['prayid'], 'owner' => (int) $r['ownerid'],
            'status' => (int) $r['status'], 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
}

if (in_array('friend', $kinds, true)) {
    // 舊站 relationship 一對一列（data/relationship.php）；0＝陌生人不搬
    $write('friend', $pdo->query('SELECT id, sender, sendee, relationship FROM relationship WHERE relationship > 0 ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'a' => (int) $r['sender'], 'b' => (int) $r['sendee'], 'level' => (int) $r['relationship']]);
}

if (in_array('guestbook', $kinds, true)) {
    // 個人頁留言板：sender 留言給 sendee（頁主），reply_msg 是頁主的回覆（10/29「留言板＝傳紙條」）
    $write('guestbook', $q('SELECT id, sender, sendee, msg, timestamp, privatemsg, reply_msg, reply_timestamp FROM guestbook', 'timestamp >= ? OR reply_timestamp >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'from' => (int) $r['sender'], 'to' => (int) $r['sendee'],
            'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp']), 'private' => (int) $r['privatemsg'] === 1,
            'reply' => legacy_text($r['reply_msg']), 'reply_at' => legacy_time($r['reply_timestamp'])]);
}

if (in_array('album', $kinds, true)) {
    // mainshow 0（一般照片）就是相簿（舊站一人最多 10 張，含大頭照）；1／2 是大頭照（帳號那邊匯）；-1 是管理員退件的大頭照，不搬
    $write('album', $pdo->query('SELECT id, ownerid, filename, is_r2 FROM photo WHERE mainshow = 0 ORDER BY ownerid, id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'],
            'image' => legacy_image('member_photo/image', (string) $r['filename'], (int) $r['is_r2'] === 1, "album_{$r['id']}")]);
}

if (in_array('date', $kinds, true)) {
    // 舊站 private_date.result：0 等回覆、1 準備參加、-1 婉拒、-2 取消／沒參加、2 過期、3 已參加
    $write('date', $q('SELECT id, sender, sendee, date, time, place, church_address, activity, single, msg, timestamp, result, result_timestamp, result_msg FROM private_date', 'timestamp >= ? OR result_timestamp >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'from' => (int) $r['sender'], 'to' => (int) $r['sendee'],
            'date' => legacy_time($r['date']), 'time' => (string) $r['time'],
            'place' => trim(legacy_text($r['place']) . ' ' . legacy_text($r['church_address'])),
            'activity' => legacy_text($r['activity']), 'single' => legacy_text($r['single']), 'message' => legacy_text($r['msg']),
            'at' => legacy_time($r['timestamp']), 'result' => (int) $r['result'], 'result_at' => legacy_time($r['result_timestamp']),
            'result_msg' => legacy_text($r['result_msg'])]);
}

if (in_array('testi', $kinds, true)) {
    // 愛的見證：status 0 待審核、1 顯示、-1 隱藏；pic 在 member_photo/testi_pic/
    $write('testi', $q('SELECT id, ownerid, boyid, girlid, status, subject, content, pic, timestamp, is_r2 FROM testi', 'timestamp >= ?', ' ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'], 'male' => (int) $r['boyid'], 'female' => (int) $r['girlid'],
            'status' => (int) $r['status'], 'title' => legacy_text($r['subject']), 'body' => legacy_text($r['content']), 'at' => legacy_time($r['timestamp']),
            'image' => legacy_image('member_photo/testi_pic', (string) $r['pic'], (int) $r['is_r2'] === 1, "testi_{$r['id']}")]);
}

file_put_contents("{$out}/content_manifest.json", json_encode(['exported_at' => date('c'), 'since' => $since, 'diary_owners' => $diaryOwners, 'kinds' => $kinds, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod("{$out}/content_manifest.json", 0600);
foreach ($counts as $k => $n) {
    printf("%s：%d\n", $k, $n);
}

// 硬碟守門（11/22：全站照片約 30 GB、vm10 只剩 6 GB）：寫照片前剩餘空間低於 --min-free-gb（預設 3）就停，不把正式機寫滿
function ensure_free_space(string $dir, float $minGb): void
{
    $free = @disk_free_space($dir);
    if ($free !== false && $free < $minGb * 1e9) {
        fprintf(STDERR, "🛑 %s 剩 %.1f GB，低於 %.1f GB，停止匯出（已寫的檔案留著，請先匯入、刪掉再分批跑）\n", $dir, $free / 1e9, $minGb);
        exit(3);
    }
}
