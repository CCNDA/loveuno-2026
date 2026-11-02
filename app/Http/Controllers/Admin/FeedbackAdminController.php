<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// 管理員看「回報問題／建議」：還沒處理、處理中在上面，已處理的列最近 30 筆
class FeedbackAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.feedback', [
            'open' => Feedback::with('user')->whereIn('status', ['open', 'doing'])->orderByRaw("status = 'doing'")->orderBy('created_at')->get(),
            'done' => Feedback::with(['user', 'handler'])->where('status', 'done')->orderByDesc('handled_at')->limit(30)->get(),
        ]);
    }

    public function status(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Feedback::STATUSES))]]);
        $feedback->forceFill([
            'status' => $data['status'],
            'handled_by' => $data['status'] === 'open' ? null : $request->user()->id,
            'handled_at' => $data['status'] === 'open' ? null : now(),
        ])->save();

        return redirect()->route('admin.feedback')->with('status', sprintf('第 %d 則改成「%s」了。', $feedback->id, Feedback::STATUSES[$data['status']]));
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
}
