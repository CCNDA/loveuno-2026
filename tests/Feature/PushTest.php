<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    private function keys(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        Artisan::call('push:vapid', ['--env-file' => $file]);
        $env = file_get_contents($file);
        unlink($file);
        preg_match('/^VAPID_PUBLIC_KEY=(.+)$/m', $env, $pub);
        preg_match('/^VAPID_PRIVATE_KEY=(.+)$/m', $env, $priv);
        config(['loveuno.push.public_key' => $pub[1], 'loveuno.push.private_key' => $priv[1]]);
    }

    public function test_manifest_and_settings_box(): void
    {
        $this->assertFileExists(public_path('manifest.json')); // 靜態檔由 nginx 送，測試裡看檔案在不在
        $u = $this->member();
        $this->actingAs($u)->get('/center/account')->assertSee('rel="manifest"', false)->assertSee('id="push-box"', false)->assertSee('push.js');
    }

    public function test_subscribe_and_push_on_new_note_with_valid_vapid(): void
    {
        $this->keys();
        Http::fake(['push.example.com/*' => Http::response('', 201), 'gone.example.com/*' => Http::response('', 410)]);
        $a = $this->member();
        $b = $this->member();
        foreach (['https://push.example.com/abc', 'https://gone.example.com/xyz'] as $ep) {
            $this->actingAs($b)->postJson('/push/subscribe', ['endpoint' => $ep, 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
        }
        $this->actingAs($b)->postJson('/push/subscribe', ['endpoint' => 'http://insecure.example.com/x', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertStatus(422);
        $this->assertSame(2, PushSubscription::count());

        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '你好']);
        Http::assertSent(function ($req) {
            if ($req->url() !== 'https://push.example.com/abc') {
                return false;
            }
            // VAPID：Authorization 是 vapid t=<JWT>, k=<公鑰>，JWT 的 aud 是推播服務的網域、簽章是 64 bytes
            preg_match('/^vapid t=([^,]+), k=(.+)$/', $req->header('Authorization')[0], $m);
            [$h, $c, $sig] = explode('.', $m[1]);
            $claims = json_decode(base64_decode(strtr($c, '-_', '+/')), true);
            $raw = base64_decode(strtr($sig, '-_', '+/') . str_repeat('=', (4 - strlen($sig) % 4) % 4));

            return $claims['aud'] === 'https://push.example.com' && strlen($raw) === 64 && $req->header('TTL')[0] === '86400';
        });
        $this->assertSame(1, PushSubscription::count()); // 410 的訂閱刪掉了

        $this->actingAs($b)->getJson('/push/summary')->assertOk()->assertJson(['body' => '您有 1 封新紙條。']);
    }

    public function test_vapid_signature_verifies_with_public_key(): void
    {
        $this->keys();
        $ref = new \ReflectionMethod(\App\Support\WebPush::class, 'jwt');
        $jwt = $ref->invoke(null, 'https://push.example.com/abc');
        [$h, $c, $sig] = explode('.', $jwt);
        $raw = base64_decode(strtr($sig, '-_', '+/') . str_repeat('=', (4 - strlen($sig) % 4) % 4));
        // r||s 轉回 DER，用公鑰驗
        $int = function (string $b): string {
            $b = ltrim($b, "\x00");
            if ($b === '' || (ord($b[0]) & 0x80)) {
                $b = "\x00" . $b; // DER 的 INTEGER 最高位元是 1 時要補 0
            }

            return "\x02" . chr(strlen($b)) . $b;
        };
        $seq = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        $der = "\x30" . chr(strlen($seq)) . $seq;
        $priv = openssl_pkey_get_private(base64_decode(config('loveuno.push.private_key')));
        $pub = openssl_pkey_get_public(openssl_pkey_get_details($priv)['key']);
        $this->assertSame(1, openssl_verify($h . '.' . $c, $der, $pub, OPENSSL_ALGO_SHA256));
    }

    public function test_unsubscribe_and_no_keys_means_no_push(): void
    {
        Http::fake();
        $a = $this->member();
        $b = $this->member();
        $this->actingAs($b)->postJson('/push/subscribe', ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'p', 'auth' => 'a']]);
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '你好']); // 沒有 VAPID 金鑰：不送
        Http::assertNothingSent();
        $this->actingAs($b)->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }
}
