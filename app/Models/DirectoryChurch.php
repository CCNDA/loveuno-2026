<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// 名錄的教會（每天從 church.oursweb.net 的 church.json 同步，directory:sync-churches）。
// 熊叔叔 11/6 決定 #11（甲）：自我介紹的教會先選國家、再從這裡挑；找不到的會員可以自己打名稱。
class DirectoryChurch extends Model
{
    protected $primaryKey = 'oid';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['oid' => 'integer', 'synced_at' => 'datetime'];
    }

    // 國家選單：有教會的國家，教會多的在前（台灣、美國、香港…）；同步後清快取
    public static function countries(): array
    {
        return Cache::remember('directory_countries', 3600, fn () => self::query()
            ->selectRaw('country_code, MAX(country) AS country, COUNT(*) AS n')
            ->where('country_code', '!=', '')->groupBy('country_code')->orderByDesc('n')
            ->pluck('country', 'country_code')->all());
    }

    // 顯示在建議清單上的地點：縣市＋區
    public function place(): string
    {
        return trim($this->city . ' ' . $this->district);
    }
}
