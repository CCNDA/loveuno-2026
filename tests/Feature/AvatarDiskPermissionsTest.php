<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// avatars 磁碟（本機）：私有檔要給群組讀寫，資料夾要給群組進得去。
// 同步／匯入用 ubuntu 跑、網站用 www-data 跑；預設 0600／0700 會讓 www-data 讀不到 → 破圖（11/20 vm10 實查）
class AvatarDiskPermissionsTest extends TestCase
{
    public function test_private_files_and_dirs_are_group_accessible(): void
    {
        $root = sys_get_temp_dir() . '/avatar-perm-' . bin2hex(random_bytes(4));
        config(['filesystems.disks.avatars.root' => $root]);
        Storage::forgetDisk('avatars');
        $old = umask(0002);
        try {
            Storage::disk('avatars')->put('a/b/photo.jpg', 'x');
            clearstatcache(); // Flysystem 在 chmod 前查過狀態，不清快取會讀到 chmod 之前的權限
            $this->assertSame('0660', substr(sprintf('%o', fileperms("{$root}/a/b/photo.jpg")), -4));
            $this->assertSame('0770', substr(sprintf('%o', fileperms("{$root}/a/b")), -4));
            $this->assertSame('0770', substr(sprintf('%o', fileperms("{$root}/a")), -4));
            $this->assertSame(0, fileperms("{$root}/a/b/photo.jpg") & 0007, '其他人沒有權限');
        } finally {
            umask($old);
            @unlink("{$root}/a/b/photo.jpg"); @rmdir("{$root}/a/b"); @rmdir("{$root}/a"); @rmdir($root);
            Storage::forgetDisk('avatars');
        }
    }

    public function test_s3_driver_class_is_installed(): void
    {
        // 公測要切到 R2（AVATAR_DISK_DRIVER=s3）：少了這個套件會整個 500（11/20 vm10 實跑踩到）
        $this->assertTrue(class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class));
        config(['filesystems.disks.r2probe' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'auto',
            'bucket' => 'b', 'endpoint' => 'https://example.invalid', 'use_path_style_endpoint' => true, 'root' => 'lu2026']]);
        $this->assertNotNull(Storage::disk('r2probe'));
    }
}
