<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VerificationController;
use App\Models\Verification;
use App\Notifications\VerificationDecided;
use App\Support\Points;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// 管理員審核身份認證（職業 Email、身分證明文件、教會週報）。照片審完就刪（通過、不通過都刪）。
class VerificationReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.verifications', [
            'pending' => Verification::with('user.profile')->where('status', 'pending')->orderBy('updated_at')->get(),
        ]);
    }

    public function decide(Request $request, Verification $verification): RedirectResponse
    {
        abort_unless($verification->status === 'pending', 409);
        // 管理員看過的是哪一版：會員在審核途中重新上傳／重填，就不能讓「通過」套到沒看過的那一份
        if (! hash_equals((string) $verification->updated_at?->getTimestamp(), (string) $request->input('version'))) {
            return redirect()->route('admin.verifications')->with('status', sprintf('%s 剛剛重新送了%s，請重新看過再決定。', $verification->user->name, $verification->label()));
        }
        $data = $request->validateWithBag('v' . $verification->id, [
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'required_if:decision,reject', Rule::in(Verification::REJECT_REASONS[$verification->kind] ?? [])],
        ], [
            'decision.required' => '請按「通過」或「不通過」其中一個按鈕。',
            'reason.required_if' => '不通過要選一個理由（會員看得到）。',
        ], ['reason' => '不通過的理由']);

        $path = $verification->file_path;
        $approved = $data['decision'] === 'approve';
        $verification->forceFill([
            'status' => $approved ? 'approved' : 'rejected',
            'reject_reason' => $approved ? '' : $data['reason'],
            'file_path' => '',
            'company_email' => $approved ? $verification->company_email : '', // 不通過就不留公司 Email
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        if ($path !== '') {
            VerificationController::deleteQuietly($path); // 先改資料再刪檔；刪不掉不讓頁面 500
        }
        if ($approved) {
            Points::checkTasks($verification->user); // 完成 XX 認證 +10（Figma「可獲得積點項目」，點數照舊站）
        }
        // 舊站 admin/*_approve.php 會寄站內信說結果；新版寄 email
        $verification->user->notify(new VerificationDecided($verification->label(), $approved, $verification->reject_reason));

        return redirect()->route('admin.verifications')->with('status', sprintf('%s 的%s：%s。', $verification->user->name, $verification->label(), $data['decision'] === 'approve' ? '通過' : '不通過'));
    }

    public function photo(Verification $verification): Response
    {
        abort_unless($verification->file_path !== '' && Storage::disk('avatars')->exists($verification->file_path), 404);

        return response(Storage::disk('avatars')->get($verification->file_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
