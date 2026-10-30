<?php
// 在 vm10（舊站正式機）上執行：匯出會員給新版愛諾園匯入用（Asana 1215470856990770）。
// 熊叔叔 2026-10-29 TG：「我會找協會會員的id 做示範」→ 指定編號；10/30「3都搬」→ 加 --all 整批。
//
// 用法（ubuntu 身分）：
//   php legacy_export.php --cnf=<cora_ro 的設定檔，host=127.0.0.1> --out=/tmp/lu2026_export 1000014 1018068
//   php legacy_export.php --cnf=... --out=... --all [--status=2,4,6]   ← 整批（預設所有狀態，500 位一批）
//   每位都帶舊站 basic.status（2 交友會員、4 社群會員、6 特約作者、1／3 未啟用、負數停權），新版匯入時照狀態處理
//
// 安全：
// - 只用唯讀帳號（熊叔叔給的 cora_ro：austwoon_unomember 整個庫只有 SELECT，要從 127.0.0.1 連，設定檔寫 host=127.0.0.1）。
//   手機（熊叔叔 10/29「2d手機號碼一起搬」）要讀 certification；讀不到就不匯手機、印一行提醒，其他照常
// - 舊站密碼是明文：在這台就先 password_hash，明文不寫進任何檔案、不離開這台
// - 只匯命令列給的編號（或 --all）；不是數字的參數直接拒絕
// - 大頭照只取 mainshow 2（通過）或 1（審核中）；沒有就不匯大頭照（一般照片是相簿，由 legacy_export_content.php --kind=album 匯）
// - 輸出目錄權限 700，用完請刪掉

declare(strict_types=1);

$opts = getopt('', ['cnf:', 'out:', 'web::', 'r2::', 'all', 'status::', 'db::'], $rest);
$db = preg_match('/^[A-Za-z0-9_]+$/', (string) ($opts['db'] ?? '')) ? $opts['db'] : 'austwoon_unomember'; // 只有測試時才指定別的庫
$ids = array_slice($argv, $rest);
$all = isset($opts['all']);
if (empty($opts['cnf']) || empty($opts['out']) || ($all === (bool) $ids)) {
    fwrite(STDERR, "用法：php legacy_export.php --cnf=<唯讀設定檔> --out=<輸出目錄> <會員編號>...｜--all [--status=2,4,6]\n");
    exit(2);
}
$statuses = null;
if (isset($opts['status']) && $opts['status'] !== false && $opts['status'] !== '') {
    if (! preg_match('/^-?\d+(,-?\d+)*$/', (string) $opts['status'])) {
        fwrite(STDERR, "--status 要是逗號分隔的數字，例如 2,4,6\n");
        exit(2);
    }
    $statuses = array_map('intval', explode(',', (string) $opts['status']));
}
foreach ($ids as $id) {
    if (! preg_match('/^\d{7}$/', $id)) {
        fwrite(STDERR, "會員編號要是 7 位數字：{$id}\n");
        exit(2);
    }
}
$ids = array_values(array_unique(array_map('intval', $ids)));
$web = rtrim($opts['web'] ?? '/home/loveuno', '/');
$r2 = rtrim($opts['r2'] ?? '', '/'); // 舊站 R2_PUBLIC_URL；照片 is_r2=1 時用

$cnf = parse_ini_file($opts['cnf'], true, INI_SCANNER_RAW);
$c = $cnf['client'] ?? [];
if (empty($c['user']) || empty($c['password'])) {
    fwrite(STDERR, "唯讀設定檔讀不到 user／password\n");
    exit(2);
}
$host = preg_match('/^[A-Za-z0-9.\-]+$/', (string) ($c['host'] ?? '')) ? $c['host'] : 'localhost'; // cora_ro 只開 127.0.0.1
$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $c['user'], $c['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
unset($c, $cnf);

if ($all) {
    $sql = 'SELECT id FROM basic' . ($statuses ? ' WHERE status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')' : '') . ' ORDER BY id';
    $st = $pdo->prepare($sql);
    $st->execute($statuses ?? []);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    fprintf(STDERR, "整批：%d 位\n", count($ids));
}

$out = rtrim($opts['out'], '/');
if (! is_dir("{$out}/photos") && ! mkdir("{$out}/photos", 0700, true)) {
    fwrite(STDERR, "建不了輸出目錄\n");
    exit(1);
}
chmod($out, 0700);

$members = [];
foreach (array_chunk($ids, 500) as $n => $batch) {
    $in = implode(',', array_fill(0, count($batch), '?'));
    $st = $pdo->prepare("SELECT b.id, b.status, b.email, b.password, b.nickname, p.sex, p.birthday, p.live_place, p.church, p.church_show, p.job, p.intro
                           FROM basic b LEFT JOIN profile p ON p.id = b.id WHERE b.id IN ({$in})");
    $st->execute($batch);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $ph = $pdo->prepare("SELECT ownerid, filename, is_r2 FROM photo WHERE ownerid IN ({$in}) AND mainshow IN (1, 2) ORDER BY ownerid, mainshow DESC, id");
    $ph->execute($batch);
    $main = [];
    foreach ($ph->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $main[(int) $p['ownerid']] ??= $p;
    }

    // 手機認證（舊站 certification：phone_certification 3 已認證、4 已過期；phone_timestamp 是認證通過的時間）
    $cert = [];
    try {
        $cs = $pdo->prepare("SELECT id, phone_certification, phone_country_code, phone_mobile, phone_timestamp FROM certification WHERE id IN ({$in})");
        $cs->execute($batch);
        foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cert[(int) $c['id']] = $c;
        }
    } catch (PDOException) {
        $cert = null;
        fwrite(STDERR, "讀不到 certification，這次沒有匯出手機\n");
    }

    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $photo = null;
        if (isset($main[$id])) {
            $f = ltrim((string) $main[$id]['filename'], '/');
            $bytes = false;
            if ((int) $main[$id]['is_r2'] === 1 && $r2 !== '') {
                $bytes = @file_get_contents($r2 . '/member_photo/image/' . str_replace('%2F', '/', rawurlencode($f)));
            } elseif (! str_contains($f, '..')) {
                $bytes = @file_get_contents("{$web}/member_photo/image/{$f}");
            }
            if ($bytes !== false && @getimagesizefromstring($bytes)) {
                $photo = "photos/{$id}.img";
                file_put_contents("{$out}/{$photo}", $bytes);
                chmod("{$out}/{$photo}", 0600);
            }
        }
        $members[] = [
            'legacy_id' => $id,
            'status' => (int) $r['status'], // 舊站 basic.status
            'email' => mb_strtolower(trim((string) $r['email'])),
            'password_hash' => $r['password'] !== '' ? password_hash((string) $r['password'], PASSWORD_BCRYPT) : null,
            'nickname' => trim((string) $r['nickname']),
            'gender' => in_array($r['sex'], ['男', '女'], true) ? $r['sex'] : '',
            'birth_year' => ($y = (int) substr((string) $r['birthday'], 0, 4)) > 1900 ? $y : null,
            'live_place' => (string) $r['live_place'],
            'church' => $r['church_show'] ? mb_substr((string) $r['church'], 0, 100) : '',
            'church_visible' => (bool) $r['church_show'],
            'job' => (string) $r['job'],
            'intro' => $r['intro'],
            'photo' => $photo,
        ] + ($cert === null ? [] : ['phone' => isset($cert[$id]) && in_array((int) $cert[$id]['phone_certification'], [3, 4], true) ? [
            'country_code' => (string) $cert[$id]['phone_country_code'],
            'mobile' => (string) $cert[$id]['phone_mobile'],
            'verified_at' => (string) $cert[$id]['phone_timestamp'],
        ] : null]);
    }
    if ($all) {
        fprintf(STDERR, "  第 %d 批完成（累計 %d 位）\n", $n + 1, count($members));
    }
}
file_put_contents("{$out}/export.json", json_encode(['exported_at' => date('c'), 'members' => $members], JSON_UNESCAPED_UNICODE | ($all ? 0 : JSON_PRETTY_PRINT)));
chmod("{$out}/export.json", 0600);

$found = array_column($members, 'legacy_id');
printf("匯出 %d 位%s；有主照 %d 位\n", count($found), $all ? '' : '：' . implode(', ', $found), count(array_filter(array_column($members, 'photo'))));
if ($missing = array_diff($ids, $found)) {
    printf("找不到：%s\n", implode(', ', $missing));
}
