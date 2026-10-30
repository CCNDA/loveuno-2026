<?php
// 在 vm10（舊站正式機）上執行：匯出會員的內容（第二階段），給新版愛諾園 legacy:import-content 匯入。
// 熊叔叔 2026-10-30 TG「3都搬」。對照表在 docs/legacy-migration.md。Asana 1215470856990770。
//
// 用法（ubuntu 身分）：
//   php legacy_export_content.php --cnf=<cora_ro 的設定檔，host=127.0.0.1> --out=/tmp/lu2026_content --kind=diary,pray [--images]
//
// 每一種寫一個 JSONL（一行一筆）：diary.jsonl、diary_reply.jsonl、pray.jsonl、pray_reply.jsonl
// - 文字照舊站存法還原：emoji_decode（用舊站自己的 h2019/emoji_lib.php）、<br> → 換行、&lt; &gt; 等還原、‵ → '
//   新版畫面一律 escape，所以還原成純文字是安全的
// - status -1（後台隱藏）的也匯出，帶 status，匯入端決定怎麼處理
// - --images：日記照片從本機 diary_pic/ 或 R2 讀出來放進 images/，匯入端重新轉檔
// - 只用唯讀帳號；輸出目錄 700、檔案 600，用完請刪掉

declare(strict_types=1);

$opts = getopt('', ['cnf:', 'out:', 'kind:', 'images', 'web::', 'r2::', 'db::']);
$kinds = array_filter(explode(',', (string) ($opts['kind'] ?? '')));
$known = ['diary', 'pray'];
if (empty($opts['cnf']) || empty($opts['out']) || ! $kinds || array_diff($kinds, $known)) {
    fwrite(STDERR, "用法：php legacy_export_content.php --cnf=<唯讀設定檔> --out=<輸出目錄> --kind=diary,pray [--images]\n");
    exit(2);
}
$db = preg_match('/^[A-Za-z0-9_]+$/', (string) ($opts['db'] ?? '')) ? $opts['db'] : 'austwoon_unomember'; // 只有測試時才指定別的庫
$web = rtrim($opts['web'] ?? '/home/loveuno', '/');
$r2 = rtrim($opts['r2'] ?? '', '/');
$withImages = isset($opts['images']);

if (is_file("{$web}/h2019/emoji_lib.php")) {
    require_once "{$web}/h2019/emoji_lib.php"; // 舊站自己的 emoji_decode，照它存的方式解回來
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
if ($withImages && ! is_dir("{$out}/images") && ! mkdir("{$out}/images", 0700)) {
    fwrite(STDERR, "建不了 images 目錄\n");
    exit(1);
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

function legacy_time(?string $t): ?string
{
    return ($t === null || $t === '' || str_starts_with($t, '0000')) ? null : $t;
}

$counts = [];
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

if (in_array('diary', $kinds, true)) {
    $imgCount = 0;
    $write('diary', $pdo->query('SELECT id, ownerid, subject, content, date, status, pic, is_r2 FROM diary ORDER BY id'),
        function (array $r) use ($withImages, $web, $r2, $out, &$imgCount): array {
            $image = null;
            $pic = ltrim((string) $r['pic'], '/');
            if ($withImages && $pic !== '' && ! str_contains($pic, '..')) {
                $bytes = ((int) $r['is_r2'] === 1 && $r2 !== '')
                    ? @file_get_contents($r2 . '/diary_pic/' . str_replace('%2F', '/', rawurlencode($pic)))
                    : @file_get_contents("{$web}/diary_pic/{$pic}");
                if ($bytes !== false && @getimagesizefromstring($bytes)) {
                    $image = "images/diary_{$r['id']}.img";
                    file_put_contents("{$out}/{$image}", $bytes);
                    chmod("{$out}/{$image}", 0600);
                    $imgCount++;
                }
            }

            return [
                'legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'],
                'title' => legacy_text($r['subject']), 'body' => legacy_text($r['content']),
                'date' => legacy_time($r['date']), 'status' => (int) $r['status'],
                'has_pic' => $pic !== '', 'image' => $image,
            ];
        });
    $counts['diary_images'] = $imgCount;
    $write('diary_reply', $pdo->query('SELECT id, diaryid, ownerid, status, msg, timestamp FROM diary_reply ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'parent' => (int) $r['diaryid'], 'owner' => (int) $r['ownerid'],
            'status' => (int) $r['status'], 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
}

if (in_array('pray', $kinds, true)) {
    $write('pray', $pdo->query('SELECT id, ownerid, status, subject, msg, timestamp FROM pray ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'owner' => (int) $r['ownerid'], 'status' => (int) $r['status'],
            'title' => legacy_text($r['subject']), 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
    $write('pray_reply', $pdo->query('SELECT id, ownerid, prayid, status, msg, timestamp FROM pray_reply ORDER BY id'),
        fn (array $r) => ['legacy_id' => (int) $r['id'], 'parent' => (int) $r['prayid'], 'owner' => (int) $r['ownerid'],
            'status' => (int) $r['status'], 'body' => legacy_text($r['msg']), 'at' => legacy_time($r['timestamp'])]);
}

file_put_contents("{$out}/content_manifest.json", json_encode(['exported_at' => date('c'), 'kinds' => $kinds, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod("{$out}/content_manifest.json", 0600);
foreach ($counts as $k => $n) {
    printf("%s：%d\n", $k, $n);
}
