<?php
// 在 vm10（舊站正式機）上執行：只匯出「指定的會員編號」，給新版愛諾園匯入用。
// 熊叔叔 2026-10-29 TG：「我會找協會會員的id 做示範」（Asana 1215470856990770）。
//
// 用法（ubuntu 身分，唯讀帳號設定檔由熊叔叔建立）：
//   php legacy_export.php --cnf=/home/ubuntu/.lu2026_ro.cnf --out=/tmp/lu2026_export 1000014 1018068
//
// 安全：
// - 只用唯讀帳號 lu2026_ro（只有 basic、profile、photo 三張表的 SELECT）
// - 舊站密碼是明文：在這台就先 password_hash，明文不寫進任何檔案、不離開這台
// - 只匯命令列給的編號；不是數字的參數直接拒絕
// - 照片只取主照（mainshow=1，沒有就取第一張），檔案從本機 member_photo/image 或 R2 公開網址讀
// - 輸出目錄權限 700，用完請刪掉

declare(strict_types=1);

$opts = getopt('', ['cnf:', 'out:', 'web::', 'r2::'], $rest);
$ids = array_slice($argv, $rest);
if (empty($opts['cnf']) || empty($opts['out']) || ! $ids) {
    fwrite(STDERR, "用法：php legacy_export.php --cnf=<唯讀設定檔> --out=<輸出目錄> <會員編號>...\n");
    exit(2);
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
$pdo = new PDO('mysql:host=localhost;dbname=austwoon_unomember;charset=utf8mb4', $c['user'], $c['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
unset($c, $cnf);

$out = rtrim($opts['out'], '/');
if (! is_dir("{$out}/photos") && ! mkdir("{$out}/photos", 0700, true)) {
    fwrite(STDERR, "建不了輸出目錄\n");
    exit(1);
}
chmod($out, 0700);

$in = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("SELECT b.id, b.email, b.password, b.nickname, p.sex, p.birthday, p.live_place, p.church, p.church_show, p.job, p.intro
                       FROM basic b LEFT JOIN profile p ON p.id = b.id WHERE b.id IN ({$in})");
$st->execute($ids);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$ph = $pdo->prepare("SELECT ownerid, filename, is_r2 FROM photo WHERE ownerid IN ({$in}) ORDER BY ownerid, mainshow DESC, id");
$ph->execute($ids);
$main = [];
foreach ($ph->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $main[(int) $p['ownerid']] ??= $p;
}

$members = [];
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
    ];
}
file_put_contents("{$out}/export.json", json_encode(['exported_at' => date('c'), 'members' => $members], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
chmod("{$out}/export.json", 0600);

$found = array_column($members, 'legacy_id');
printf("匯出 %d 位：%s；有主照 %d 位\n", count($found), implode(', ', $found), count(array_filter(array_column($members, 'photo'))));
if ($missing = array_diff($ids, $found)) {
    printf("找不到：%s\n", implode(', ', $missing));
}
