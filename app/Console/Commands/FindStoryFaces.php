<?php

namespace App\Console\Commands;

use App\Models\LoveStory;
use App\Support\FaceFocus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// 已經有的見證照片補找臉：php artisan love-stories:find-faces [--all]（預設只找還沒設焦點的；--all 連管理員點過的也重找）
class FindStoryFaces extends Command
{
    protected $signature = 'love-stories:find-faces {--all : 連已經設過焦點的也重找} {--dry-run : 只印結果不存}';

    protected $description = '愛的見證照片自動找臉，設裁切焦點';

    public function handle(): int
    {
        $q = LoveStory::whereNotNull('photo_path');
        if (! $this->option('all')) {
            $q->whereNull('photo_focus_x');
        }
        foreach ($q->orderBy('id')->get() as $s) {
            $f = FaceFocus::find(Storage::disk('avatars')->path($s->photo_path));
            $this->line(sprintf('#%d %s → %s', $s->id, $s->title, $f ? "{$f[0]}%, {$f[1]}%" : '找不到臉（用預設）'));
            if ($f && ! $this->option('dry-run')) {
                $s->forceFill(['photo_focus_x' => $f[0], 'photo_focus_y' => $f[1]])->save();
            }
        }

        return self::SUCCESS;
    }
}
