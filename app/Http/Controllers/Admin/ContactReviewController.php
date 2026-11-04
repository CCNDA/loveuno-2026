<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// 管理員看「聯絡我們」的來信（信也會寄到 support@loveuno.com；這裡是備份、標記處理過了沒）
class ContactReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.contacts', [
            'open' => ContactMessage::whereNull('handled_at')->orderBy('created_at')->get(),
            'done' => ContactMessage::whereNotNull('handled_at')->orderByDesc('handled_at')->limit(20)->get(),
        ]);
    }

    public function handle(ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['handled_at' => now()])->save();

        return redirect()->route('admin.contacts')->with('status', sprintf('第 %d 封標成處理過了。', $message->id));
    }
}
