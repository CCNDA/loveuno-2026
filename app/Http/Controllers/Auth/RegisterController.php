<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// 註冊＋email 驗證（年底開放用，Asana 1215470856990770）。
// 11/8 展示版不開放：config('loveuno.registration_open') 是 false 時整組路由回 404。
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register', ['years' => $this->birthYears()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $years = $this->birthYears();
        $data = $request->validate([
            // 舊站 chkemail 會擋含「+」的 email（已知 bug），這裡用標準規則
            'email' => ['required', 'string', 'email:rfc', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(10)],
            'name' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:男,女'],
            'birth_year' => ['required', 'integer', 'between:' . min($years) . ',' . max($years)],
            'agree' => ['accepted'],
        ], [
            'email.unique' => '這個電子郵件已經註冊過了，可以直接登入。',
            'birth_year.between' => '愛諾園只開放 18 歲以上的會員。',
            'agree.accepted' => '請先閱讀並同意會員規範。',
        ], [
            'email' => '電子郵件', 'password' => '密碼', 'name' => '暱稱',
            'gender' => '性別', 'birth_year' => '出生年', 'agree' => '會員規範',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
            ]);
            Profile::create([
                'user_id' => $user->id,
                'gender' => $data['gender'],
                'birth_year' => (int) $data['birth_year'],
            ]);

            return $user;
        });

        event(new Registered($user)); // 寄驗證信（User 實作 MustVerifyEmail）
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    // 出生年選單：18～80 歲
    private function birthYears(): array
    {
        $now = (int) now()->format('Y');

        return range($now - 18, $now - 80);
    }
}
