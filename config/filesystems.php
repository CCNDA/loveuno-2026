<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // 大頭照：私有，不放 public/。測試站存本機；正式站可以用 AVATAR_DISK_DRIVER=s3 換成 R2 私有 bucket
        'avatars' => [
            'driver' => env('AVATAR_DISK_DRIVER', 'local'),
            // R2（s3）時 root 是 bucket 裡的前綴（例如 lu2026，跟舊站證件分開）；本機時是資料夾。公測：熊叔叔 11/20「可以直接用舊版的R2的 bucket」
            'root' => env('AVATAR_DISK_DRIVER', 'local') === 's3' ? env('AVATAR_S3_ROOT', 'lu2026') : storage_path('app/private/avatars'),
            'visibility' => 'private',
            // 本機時：私有也要給群組讀寫。同步／匯入用 ubuntu 跑、網站用 www-data（php-fpm）跑，
            // 預設 private 是 0600／0700，ubuntu 建的資料夾 www-data 會讀不到 → 破圖（11/20 vm10 實查）。
            // 群組靠 storage 資料夾的 setgid 繼承 www-data；其他人仍然沒有權限
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0660],
                'dir' => ['public' => 0755, 'private' => 0770],
            ],
            'key' => env('AVATAR_S3_KEY'),
            'secret' => env('AVATAR_S3_SECRET'),
            'region' => env('AVATAR_S3_REGION', 'auto'),
            'bucket' => env('AVATAR_S3_BUCKET'),
            'endpoint' => env('AVATAR_S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true, // 存檔失敗要報錯，不然資料庫會指到不存在的檔
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
