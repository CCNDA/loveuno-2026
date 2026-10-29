<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// 管理員看檢舉、標成已處理（只有 is_admin 的帳號進得來）
class ReportReviewController extends Controller
{
    public function index(): View
    {
        $open = Report::with(['reporter:id,name', 'reported:id,name'])->where('status', 'open')->orderBy('created_at')->get();
        $closed = Report::with(['reporter:id,name', 'reported:id,name', 'handler:id,name'])->where('status', 'closed')
            ->orderByDesc('handled_at')->limit(20)->get();

        return view('admin.reports', ['open' => $open, 'closed' => $closed]);
    }

    public function close(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:500'],
        ], [
            'admin_note.required' => '請寫一下怎麼處理的（例如：已警告、已停權、查無不當）。',
        ], ['admin_note' => '處理方式']);
        abort_unless($report->status === 'open', 409);

        $report->forceFill([
            'status' => 'closed',
            'admin_note' => trim($data['admin_note']),
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return redirect()->route('admin.reports')->with('status', sprintf('第 %d 號檢舉標成已處理了。', $report->id));
    }

    public function screenshot(Report $report): Response
    {
        abort_unless($report->screenshot_path && Storage::disk('avatars')->exists($report->screenshot_path), 404);

        return response(Storage::disk('avatars')->get($report->screenshot_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
