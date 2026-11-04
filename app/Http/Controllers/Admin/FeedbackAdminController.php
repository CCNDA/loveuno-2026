<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// 管理員看「回報問題／建議」，彙整成待辦（熊叔叔 11/2 fb-A）：可以篩狀態、類型、會員，看同一個人送了幾則，匯出 CSV
class FeedbackAdminController extends Controller
{
    public function index(Request $request): View
    {
        $f = $this->filters($request);

        return view('admin.feedback', [
            'items' => $this->query($f)->with(['user', 'handler'])
                ->orderByRaw("FIELD(status, 'open', 'doing', 'done')")->orderByDesc('created_at')
                ->paginate(30)->withQueryString(),
            'filters' => $f,
            // 同一個人送了幾則（列表上每一則旁邊顯示）
            'perUser' => Feedback::whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->pluck('n', 'user_id'),
        ]);
    }

    // 匯出目前篩出來的（Excel 開得起來：UTF-8 加 BOM）
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($this->filters($request))->with('user')->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['編號', '時間', '狀態', '類型', '會員編號', '暱稱', 'Email', '頁面', '裝置', '環境', '內容', '有截圖']);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), [$r->id, $r->created_at->shown()->format('Y-m-d H:i'), Feedback::STATUSES[$r->status] ?? $r->status, $r->kindLabel(),
                    $r->user_id, $r->user?->name, $r->email, $r->page, $r->deviceLabel(), $r->context, $r->body, $r->screenshot_path ? '有' : '']));
            }
            fclose($out);
        }, 'feedback-' . now()->shown()->format('Ymd-Hi') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function status(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Feedback::STATUSES))]]);
        $feedback->forceFill([
            'status' => $data['status'],
            'handled_by' => $data['status'] === 'open' ? null : $request->user()->id,
            'handled_at' => $data['status'] === 'open' ? null : now(),
        ])->save();

        return back()->with('status', sprintf('第 %d 則改成「%s」了。', $feedback->id, Feedback::STATUSES[$data['status']]));
    }

    public function screenshot(Feedback $feedback): Response
    {
        abort_unless($feedback->screenshot_path && Storage::disk('avatars')->exists($feedback->screenshot_path), 404);

        return response(Storage::disk('avatars')->get($feedback->screenshot_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filters(Request $request): array
    {
        $f = $request->validate([
            'show' => ['nullable', Rule::in(['todo', 'done', 'all'])],
            'kind' => ['nullable', Rule::in(array_keys(Feedback::KINDS))],
            'user' => ['nullable', 'integer', 'min:1'],
        ]);
        $f['show'] ??= 'todo';

        return $f;
    }

    private function query(array $f): Builder
    {
        return Feedback::query()
            ->when($f['show'] === 'todo', fn ($q) => $q->whereIn('status', ['open', 'doing']))
            ->when($f['show'] === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($f['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u));
    }

    // 會員寫的內容如果用 = + - @ 開頭，Excel 會當成公式執行：前面加一個 '
    private static function csvSafe(string $s): string
    {
        return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
    }
}
