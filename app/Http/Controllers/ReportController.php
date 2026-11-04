<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 檢舉會員（設計稿 PageReport）。封鎖了對方（或被對方封鎖）也可以檢舉：被騷擾的人常常是先封鎖再檢舉。
// 檢舉人是誰只有管理員看得到，被檢舉的人不會收到通知。
class ReportController extends Controller
{
    public function create(Request $request, User $user): View
    {
        $this->authorizeTarget($request, $user);

        return view('reports.create', ['member' => $user, 'reasons' => Report::REASONS]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTarget($request, $user);
        $data = $request->validate([
            // 順序跟畫面上的欄位一樣，錯誤摘要才會照順序（小布 0.15.0 複測建議）
            'description' => ['required', 'string', 'max:500'],
            'reason' => ['required', 'in:' . implode(',', array_keys(Report::REASONS))],
            'screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'reason.required' => '請選擇檢舉原因。',
            'reason.in' => '請選擇檢舉原因。',
            'description.max' => '內容描述不能超過 500 字。',
            'screenshot.mimes' => '圖片請選 JPG 或 PNG。',
            'screenshot.max' => '圖片太大了，請選 5MB 以下的。',
            'screenshot.dimensions' => '圖片的寬、高不能超過 6000 像素。',
        ], ['reason' => '檢舉原因', 'description' => '內容描述', 'screenshot' => '圖片']);

        $path = null;
        if ($request->hasFile('screenshot')) {
            try {
                $jpeg = AvatarImage::fit($request->file('screenshot')->getRealPath(), 1600); // 截圖要看得清楚字，比日記附圖大一點
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['screenshot' => '這個圖片讀不出來，請換一張試試看。']);
            }
            $path = 'reports/' . Str::random(40) . '.jpg';
            Storage::disk('avatars')->put($path, $jpeg);
        }

        $report = new Report(['reason' => $data['reason'], 'description' => trim($data['description'])]);
        $report->forceFill([
            'reporter_id' => $request->user()->id,
            'reported_id' => $user->id,
            'screenshot_path' => $path,
        ])->save();

        return redirect()->route('members.index')->with('status', '謝謝您告訴我們，管理員會儘快處理。檢舉內容會保密，對方不會知道是誰檢舉的。');
    }

    // 不能檢舉自己
    private function authorizeTarget(Request $request, User $user): void
    {
        abort_if((int) $user->id === (int) $request->user()->id, 404);
    }
}
