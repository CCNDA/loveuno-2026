<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Note;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 推播訂閱（public/js/push.js 送來）和推播內容（public/sw.js 收到推播後來抓）。
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        $hash = hash('sha256', $data['endpoint']);
        // 同一個裝置換帳號登入：訂閱換到現在這個人
        PushSubscription::where('endpoint_hash', $hash)->delete();
        $sub = new PushSubscription;
        $sub->forceFill([
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'endpoint_hash' => $hash,
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'user_agent' => substr((string) $request->userAgent(), 0, 200),
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', (string) $request->input('endpoint')))->delete();

        return response()->json(['ok' => true]);
    }

    // 推播要顯示什麼：只說有幾封新紙條、幾份新禮物（不放內容、不放是誰，螢幕鎖著別人也看得到通知）
    public function summary(Request $request): JsonResponse
    {
        $me = $request->user();
        $notes = Note::unreadCountFor($me);
        $gifts = Gift::unopenedCountFor($me);
        $parts = array_filter([$notes ? $notes . ' 封新紙條' : null, $gifts ? $gifts . ' 份新禮物' : null]);

        return response()->json([
            'title' => '愛諾園',
            'body' => $parts ? '您有 ' . implode('、', $parts) . '。' : '您有新的消息。',
            'url' => $notes ? route('notes.index') : ($gifts ? route('gifts.index') : route('home')),
        ]);
    }
}
