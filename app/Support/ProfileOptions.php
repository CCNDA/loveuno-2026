<?php

namespace App\Support;

// 個人資料、自我介紹的選項（設計稿 PageMemberProfile ProfileForm／IntroductionForm、PagePersonalProfile／Introduction）。
// 清單照舊站 website/data/*.txt（religion、marriage、kids、education、blood、smoke、drink、language、hobby、sports），
// 舊站資料匯入時才對得上；設計稿只寫了幾個例子。計劃生育舊站沒有，照設計稿。記在 docs/design-deviations.md #36。
class ProfileOptions
{
    public const FAITH = ['基督徒', '慕道友'];

    public const MARITAL = ['未婚', '離婚', '喪偶'];

    // 「已婚」只有管理員能設（愛的見證通過後；檢測表 D-16），會員自己的選單沒有
    public const MARITAL_ADMIN = ['已婚'];

    // 會員自己改個人資料時能選的婚姻狀況：管理員設成已婚的，要能原樣存回去（兩面審查 11/2：不然一改資料就存不了）
    public static function maritalFor(string $current): array
    {
        return in_array($current, self::MARITAL_ADMIN, true) ? array_merge(self::MARITAL, [$current]) : self::MARITAL;
    }

    public const CHILDREN = ['0', '1', '2', '3', '4', '超過4個', '保留'];

    public const EDUCATION = ['小學', '中學', '高中', '高職', '五專', '二專', '大學', '碩士', '博士', '保留'];

    public const BLOOD = ['A', 'AB', 'B', 'O', '不確定'];

    public const SMOKING = ['從來不抽', '偶爾', '煙癮很大', '保留'];

    public const DRINKING = ['從來不喝', '淺嚐', '有需要才喝', '酒癮很大', '保留'];

    public const FAMILY_PLANNING = ['有', '沒有', '不確定'];

    public const LANGUAGES = ['中文/普通話', '廣東話', '台語/閩南話', '客家話', '英文', '日文', '韓文', '馬來文', '法文', '西班牙文', '德文', '其他'];

    public const HOBBIES = ['音樂欣賞', '戶外活動', '電視電影', '泡茶聊天', '逛街採購', '電腦網路', '各類蒐集', '藝文活動', '球類運動', '唱歌跳舞', '吃吃喝喝', '打牌賭博', '睡覺打盹', '看書寫作', '各地旅遊', '露營烤肉', '攝影繪畫', '電玩對戰', '讀經靈修', '其他'];

    public const SPORTS = ['籃球', '棒球', '網球', '高爾夫球', '羽毛球', '足球', '美式足球', '板球', '慢跑', '自行車', '划船', '登山', '釣魚', '乒乓球', '游泳', '體操/健身', '太極拳', '賽車', '撞球', '其他'];

    // 「請選擇1-3種」
    public const MAX_PICKS = 3;

    public const BIBLE_BOOKS = [
        '創世記', '出埃及記', '利未記', '民數記', '申命記', '約書亞記', '士師記', '路得記', '撒母耳記上', '撒母耳記下',
        '列王紀上', '列王紀下', '歷代志上', '歷代志下', '以斯拉記', '尼希米記', '以斯帖記', '約伯記', '詩篇', '箴言',
        '傳道書', '雅歌', '以賽亞書', '耶利米書', '耶利米哀歌', '以西結書', '但以理書', '何西阿書', '約珥書', '阿摩司書',
        '俄巴底亞書', '約拿書', '彌迦書', '那鴻書', '哈巴谷書', '西番雅書', '哈該書', '撒迦利亞書', '瑪拉基書',
        '馬太福音', '馬可福音', '路加福音', '約翰福音', '使徒行傳', '羅馬書', '哥林多前書', '哥林多後書', '加拉太書', '以弗所書',
        '腓立比書', '歌羅西書', '帖撒羅尼迦前書', '帖撒羅尼迦後書', '提摩太前書', '提摩太後書', '提多書', '腓利門書', '希伯來書', '雅各書',
        '彼得前書', '彼得後書', '約翰一書', '約翰二書', '約翰三書', '猶大書', '啟示錄',
    ];
}
