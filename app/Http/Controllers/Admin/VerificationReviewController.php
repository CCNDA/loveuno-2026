<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Verification;
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
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'required_if:decision,reject', Rule::in(Verification::REJECT_REASONS[$verification->kind] ?? [])],
        ], [
            'decision.required' => '請按「通過」或「不通過」其中一個按鈕。',
            'reason.required_if' => '不通過要選一個理由（會員看得到）。',
        ], ['reason' => '不通過的理由']);

        $path = $verification->file_path;
        $verification->forceFill([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'reject_reason' => $data['decision'] === 'reject' ? $data['reason'] : '',
            'file_path' => '',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        if ($path !== '') {
            Storage::disk('avatars')->delete($path); // 先改資料再刪檔
        }

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
