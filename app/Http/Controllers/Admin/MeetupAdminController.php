<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use Illuminate\View\View;

// 管理員看所有約會（舊站 admin/list_date.php：只能看，不能改）
class MeetupAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.meetups', ['items' => Meetup::with(['sender', 'recipient'])->orderByDesc('id')->paginate(20)]);
    }
}
