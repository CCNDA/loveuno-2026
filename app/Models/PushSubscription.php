<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

// Web Push 訂閱（一台裝置一筆）。欄位都由 PushController 指定，不開 Fillable。
#[Hidden(['endpoint', 'p256dh', 'auth'])]
class PushSubscription extends Model
{
}
