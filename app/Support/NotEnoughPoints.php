<?php

namespace App\Support;

use RuntimeException;

// Points::spend 積點不夠時丟這個（只接這個，資料庫錯誤照常往上丟）
class NotEnoughPoints extends RuntimeException
{
}
