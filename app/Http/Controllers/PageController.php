<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

// 公開頁（設計稿 PageAbout、PageFaq、PageWebsitePolicy、PageContactUs）：不用登入也看得到
class PageController extends Controller
{
    public function contact(): View
    {
        return view('pages.contact');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('contact')->with('status', '我們已經收到您的聯絡內容，客服人員會儘快與您聯絡！'); // 機器人：裝作收到
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:100'],
            'subject' => ['required', 'in:' . implode(',', array_keys(ContactMessage::SUBJECTS))],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'subject.in' => '請選擇聯絡事項。',
            'body.min' => '請詳述您的問題，至少 10 個字。',
        ], ['name' => '姓名', 'email' => 'Email', 'subject' => '聯絡事項', 'body' => '聯絡內容']);

        $msg = new ContactMessage($data);
        $msg->user_id = $request->user()?->id;
        $msg->save();
        // 寄到愛諾園的信箱（support@loveuno.com）；回覆時直接回信給會員填的 Email
        defer(fn () => Notification::route('mail', config('loveuno.contact_email'))->notify(new ContactReceived($msg)));

        return redirect()->route('contact')->with('status', '我們已經收到您的聯絡內容，客服人員會儘快與您聯絡！');
    }
}
