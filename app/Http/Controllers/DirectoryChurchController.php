<?php

namespace App\Http\Controllers;

use App\Models\DirectoryChurch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 自我介紹「教會名稱」打字時的建議（熊叔叔 11/6 決定 #11）：在選的國家裡找名錄的教會，名稱開頭符合的排前面
class DirectoryChurchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $country = strtoupper(substr((string) $request->query('country', ''), 0, 2));
        if (mb_strlen($q) < 1 || mb_strlen($q) > 50) {
            return response()->json([]);
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $start = addcslashes($q, '%_\\') . '%';
        $rows = DirectoryChurch::query()
            ->when($country !== '', fn ($w) => $w->where('country_code', $country))
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('alias', 'like', $like))
            ->orderByRaw('CASE WHEN name LIKE ? OR alias LIKE ? THEN 0 ELSE 1 END', [$start, $start])
            ->orderByRaw('CHAR_LENGTH(name)')->orderBy('oid')
            ->limit(15)->get();

        return response()->json($rows->map(fn (DirectoryChurch $c) => ['oid' => $c->oid, 'name' => $c->name, 'place' => $c->place()])->values());
    }
}
