<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Note;
use App\Models\PushSubscription;
use App\Support\WebPush;
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
        if (! WebPush::allowedEndpoint($data['endpoint'])) {
            return response()->json(['ok' => false, 'message' => '這不是認得的推播服務。'], 422);
        }
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
        // 一個人最多 5 台裝置（不然一則紙條要等很多台一台一台送）：多的刪掉最舊的
        $ids = PushSubscription::where('user_id', $request->user()->id)->orderByDesc('id')->pluck('id');
        PushSubscription::whereIn('id', $ids->slice(5))->delete();
        // 記在這次登入的 session：登出時把這台裝置的訂閱刪掉（換人用同一台，前一個人不會再收到）
        $request->session()->push('push_subscription_ids', $sub->id);

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
        $dates = \App\Models\Meetup::where('recipient_id', $me->id)->where('status', 'pending')->whereDate('meet_on', '>', today())->whereNull('recipient_deleted_at')->count();
        $parts = array_filter([$notes ? $notes . ' 封新紙條' : null, $gifts ? $gifts . ' 份新禮物' : null, $dates ? $dates . ' 個約會邀請等您回覆' : null]);

        return response()->json([
            'title' => '愛諾園',
            'body' => $parts ? '您有 ' . implode('、', $parts) . '。' : '您有新的消息。',
            'url' => $notes ? route('notes.index') : ($gifts ? route('gifts.index') : ($dates ? route('meetups.index') : route('home'))),
        ]);
    }
}
