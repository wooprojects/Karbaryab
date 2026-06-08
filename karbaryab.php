<?php
// ==================== تنظیمات اولیه ====================
$TOKEN = '143111132:G--iCnsIrMT_f4Zajbh44sUnxDmxM0citmc'; // توکن خود را اینجا وارد کنید
$API = "https://tapi.bale.ai/bot{$TOKEN}/";
$BOT_USERNAME = 'toolsboxbot'; // نام کاربری ربات خود را بدون @ وارد کنید
$CACHE_DIR = __DIR__ . '/cache/'; // پوشه کش
if (!file_exists($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

// دریافت ورودی وب‌هوک
$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(200);
    exit;
}

$update = json_decode($input, true);
if (!$update) {
    http_response_code(200);
    exit;
}

// ==================== پردازش callback_query ====================
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];
    $callback_id = $callback['id'];

    // پاسخ سریع به callback
    api_request($API . 'answerCallbackQuery', [
        'callback_query_id' => $callback_id
    ]);

    // مدیریت دکمه‌ها
    if (strpos($data, 'json:') === 0) {
        $identifier = substr($data, 5);
        $info = get_user_info_by_identifier($identifier);
        if ($info) {
            send_json_info($chat_id, $info, true);
        } else {
            send_message($chat_id, "❌ اطلاعات یافت نشد.");
        }
        exit;
    }

    if (strpos($data, 'photos:') === 0) {
        $identifier = substr($data, 7);
        $info = get_user_info_by_identifier($identifier);
        if ($info && is_numeric($info['id'])) {
            send_all_profile_photos($chat_id, $info['id'], $identifier);
        } else {
            send_message($chat_id, "❌ امکان دریافت عکس‌ها وجود ندارد.");
        }
        exit;
    }

    switch ($data) {
        case 'start':
            send_start_message($chat_id, $update['callback_query']['from']['first_name'] ?? 'کاربر');
            break;
        case 'help':
            send_help_message($chat_id);
            break;
        case 'about':
            send_about_message($chat_id);
            break;
        case 'search':
            $search_text = "🔍 *جستجوی کاربر*\n\n"
                         . "لطفاً یکی از موارد زیر را ارسال کنید:\n"
                         . "• نام کاربری: `username` یا `@username`\n"
                         . "• آدرس صفحه: `ble.ir/username`\n"
                         . "🔹 برای دریافت JSON از دستور `bot [شناسه]` استفاده کنید.";
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
                    ]
                ]
            ];
            send_message($chat_id, $search_text, $keyboard);
            break;
        case 'share':
            send_share_message($chat_id);
            break;
    }
    exit;
}

// ==================== پردازش پیام‌های متنی ====================
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    $user_first_name = $message['from']['first_name'] ?? 'کاربر';

    // بررسی ریپلای
    if (isset($message['reply_to_message'])) {
        $reply = $message['reply_to_message'];
        $from = $reply['from'] ?? null;
        if ($from && isset($from['id'])) {
            $info = get_user_info_by_identifier($from['id']);
            if ($info) {
                send_photo_and_info($chat_id, $info);
            } else {
                send_message($chat_id, "❌ اطلاعات فرستنده یافت نشد.");
            }
            exit;
        }
    }

    // دستورات
    if ($text === '/start') {
        send_start_message($chat_id, $user_first_name);
        exit;
    }

    if ($text === '/help') {
        send_help_message($chat_id);
        exit;
    }

    if ($text === '/about') {
        send_about_message($chat_id);
        exit;
    }

    // بررسی دستور bot [شناسه]
    if (strtolower(substr($text, 0, 4)) === 'bot ') {
        $identifier = trim(substr($text, 4));
        $info = get_user_info_by_identifier($identifier);
        if (!$info) {
            send_message($chat_id, "❌ *کاربر یافت نشد*\n\nشناسه `{$identifier}` معتبر نیست.");
            exit;
        }
        send_json_info($chat_id, $info, false);
        exit;
    }

    // حالت عادی
    $info = get_user_info_by_identifier($text);

    if (!$info) {
        $error_text = "❌ *کاربر یافت نشد*\n\n"
                    . "شناسه `{$text}` معتبر نیست یا کاربری با این مشخصات وجود ندارد.\n"
                    . "لطفاً از درستی شناسه اطمینان حاصل کنید.";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 جستجوی دوباره', 'callback_data' => 'search'],
                    ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
                ]
            ]
        ];
        send_message($chat_id, $error_text, $keyboard);
        exit;
    }

    // ارسال عکس و اطلاعات
    send_photo_and_info($chat_id, $info);
}

http_response_code(200);
exit;

// ==================== توابع اصلی ====================

function send_photo_and_info($chat_id, $info) {
    if (!empty($info['photo'])) {
        send_photo($chat_id, $info['photo'], $info['photo_caption']);
    } else {
        $no_photo_text = "🖼 *عکس پروفایل* \n\nاین کاربر عکس پروفایل ندارد.";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
                ]
            ]
        ];
        send_message($chat_id, $no_photo_text, $keyboard);
    }

    // ارسال اطلاعات کامل
    send_detailed_info($chat_id, $info);
}

function send_start_message($chat_id, $name) {
    $text = "✨ *به ربات کاربریاب خوش آمدید، {$name} جان!* ✨\n\n"
          . "🔍 با این ربات می‌توانید اطلاعات کاربران بله را استخراج کنید.\n\n"
          . "📌 *کافیست یکی از موارد زیر را ارسال کنید:*\n"
          . "• نام کاربری: `username` یا `@username`\n"
          . "• آدرس صفحه: `ble.ir/username`\n"
          . "• روی یک پیام ریپلای کنید\n\n"
          . "⚡️ *امکانات:*\n"
          . "✅ استخراج آیدی عددی با ۵ روش پشتیبان\n"
          . "✅ نمایش عکس پروفایل و تمام عکس‌ها\n"
          . "✅ دکمه چت مستقیم در وب با آیدی عددی\n"
          . "✅ دکمه‌های کپی برای هر آیتم\n"
          . "✅ دریافت اطلاعات به صورت JSON (با دستور `bot [username]` یا دکمه)\n"
          . "✅ طراحی مدرن و کاربرپسند\n\n"
          . "🚀 *همین حالا امتحان کنید!*";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 جستجوی کاربر', 'callback_data' => 'search'],
                ['text' => '📖 راهنما', 'callback_data' => 'help']
            ],
            [
                ['text' => 'ℹ️ درباره ربات', 'callback_data' => 'about'],
                ['text' => '📤 اشتراک‌گذاری', 'callback_data' => 'share']
            ]
        ]
    ];

    send_message($chat_id, $text, $keyboard);
}

function send_help_message($chat_id) {
    $text = "📖 *راهنمای استفاده*\n\n"
          . "برای دریافت اطلاعات یک کاربر، یکی از روش‌های زیر را امتحان کنید:\n\n"
          . "🔹 ارسال نام کاربری:\n"
          . "   `username` یا `@username`\n\n"
          . "🔹 ارسال آدرس صفحه:\n"
          . "   `ble.ir/username`\n\n"
          . "🔹 دریافت JSON برای بات‌ها:\n"
          . "   `bot [username]`\n\n"
          . "📌 *توجه:* اطلاعات کاربران از صفحه عمومی استخراج می‌شود.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 جستجوی جدید', 'callback_data' => 'search'],
                ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
            ]
        ]
    ];

    send_message($chat_id, $text, $keyboard);
}

function send_about_message($chat_id) {
    $text = "ℹ️ *درباره ربات کاربریاب*\n\n"
          . "🤖 *نام:* کاربریاب\n"
          . "📌 *نسخه:* ۱.۰.۰ (نسخه نهایی مخصوص کاربران)\n"
          . "⚡️ *ساخته شده با:* ❤️ و PHP\n\n"
          . "✨ *ویژگی‌های ویژه:*\n"
          . "• استخراج آیدی عددی با ۵ روش پشتیبان\n"
          . "• دکمه چت مستقیم در وب با آیدی عددی\n"
          . "• نمایش تمام عکس‌های پروفایل\n"
          . "• دکمه‌های کپی برای تمام آیتم‌ها\n"
          . "• دریافت JSON برای بات‌ها\n"
          . "• کش کردن اطلاعات برای سرعت بیشتر\n"
          . "• طراحی مدرن و واکنش‌گرا\n\n"
          . "👨‍💻 *توسعه‌دهنده:* [محمدحسن همتی](apiot.ir/about-me)\n"
          . "🌟 اگر از ربات راضی هستید، ما را به دوستان خود معرفی کنید!";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 جستجوی کاربر', 'callback_data' => 'search'],
                ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
            ],
            [
                ['text' => '📤 اشتراک‌گذاری', 'callback_data' => 'share']
            ]
        ]
    ];

    send_message($chat_id, $text, $keyboard);
}

function send_share_message($chat_id) {
    global $BOT_USERNAME;
    $text = "✨ *ربات کاربریاب* ✨\n\n"
          . "با این ربات فوق‌حرفه‌ای می‌توانید اطلاعات کاربران بله را استخراج کنید!\n"
          . "🚀 همین حالا امتحان کنید: @{$BOT_USERNAME}\n\n"
          . "🔍 *ویژگی‌ها:*\n"
          . "• نمایش عکس پروفایل و تمام عکس‌ها\n"
          . "• آیدی عددی، نام، نام کاربری، بیو\n"
          . "• دکمه‌های کپی برای همه آیتم‌ها\n"
          . "• دریافت اطلاعات به صورت JSON\n"
          . "• پشتیبانی از ریپلای";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 جستجوی کاربر', 'callback_data' => 'search'],
                ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
            ]
        ]
    ];

    send_message($chat_id, $text, $keyboard);
}

// ==================== توابع پالایش عنوان ====================

/**
 * حذف پیشوند "بله |" از عنوان
 */
function clean_title($title) {
    $title = preg_replace('/\s*بله\s*\|\s*/u', '', $title);
    return trim($title);
}

// ==================== توابع استخراج اطلاعات ====================

/**
 * تشخیص نوع شناسه و دریافت اطلاعات کاربر (فقط private)
 */
function get_user_info_by_identifier($identifier) {
    $clean_id = ltrim($identifier, '@');

    // بررسی کش
    $cache_key = md5($clean_id);
    $cache_file = $GLOBALS['CACHE_DIR'] . $cache_key . '.json';
    if (file_exists($cache_file) && time() - filemtime($cache_file) < 3600) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached) return $cached;
    }

    // بررسی آدرس ble.ir/username
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?ble\.ir\/([a-zA-Z0-9_]+)/i', $identifier, $matches)) {
        $clean_id = $matches[1];
    }

    // اولویت با اسکرپینگ صفحه
    if (preg_match('/^[a-zA-Z0-9_]+$/', $clean_id)) {
        $info = scrape_user_info($clean_id);
        if ($info && $info['id'] !== 'نامشخص') {
            // فقط کاربران عادی را قبول کن
            if ($info['type'] === 'private') {
                save_to_cache($cache_file, $info);
                return $info;
            }
        }
    }

    // اگر عددی است، از API استفاده کن
    if (is_numeric($clean_id)) {
        $info = get_user_info_by_api($clean_id);
        if ($info && $info['type'] === 'private') {
            save_to_cache($cache_file, $info);
            return $info;
        }
    }

    // با نام کاربری از API تلاش کن (اگر بات عضو باشد)
    $info = get_user_info_by_api($clean_id);
    if ($info && $info['type'] === 'private') {
        save_to_cache($cache_file, $info);
        return $info;
    }

    return false;
}

function save_to_cache($cache_file, $info) {
    file_put_contents($cache_file, json_encode($info, JSON_UNESCAPED_UNICODE));
}

/**
 * دریافت اطلاعات از طریق API بله (getChat) و بررسی کاربر بودن
 */
function get_user_info_by_api($chat_id) {
    global $API;
    $response = api_request($API . 'getChat', ['chat_id' => $chat_id]);
    $data = json_decode($response, true);
    if (!$data || !$data['ok']) {
        return false;
    }
    $chat = $data['result'];

    // فقط کاربران خصوصی
    if (($chat['type'] ?? '') !== 'private') {
        return false;
    }

    $info = [
        'type'        => 'private',
        'id'          => $chat['id'] ?? 'نامشخص',
        'title'       => $chat['first_name'] ?? ($chat['title'] ?? ''),
        'username'    => $chat['username'] ?? '',
        'bio'         => $chat['bio'] ?? ($chat['description'] ?? ''),
        'photo'       => null,
        'photo_caption' => '',
        'verified'    => false // در بله فعلاً وجود ندارد
    ];

    // پالایش عنوان
    $info['title'] = clean_title($info['title']);

    if (isset($chat['photo'])) {
        if (isset($chat['photo']['big_file_id'])) {
            $info['photo'] = $chat['photo']['big_file_id'];
        } elseif (isset($chat['photo']['small_file_id'])) {
            $info['photo'] = $chat['photo']['small_file_id'];
        }
    }

    $info['photo_caption'] = generate_photo_caption($info['title'], $info['username'], $info['id']);
    return $info;
}

/**
 * استخراج اطلاعات کاربر از صفحه عمومی
 */
function scrape_user_info($username) {
    $url = "https://ble.ir/{$username}";
    $html = fetch_url($url);
    if (!$html) return false;

    $info = [
        'type'        => 'private', // فرض بر کاربر
        'id'          => 'نامشخص',
        'title'       => '',
        'username'    => $username,
        'bio'         => '',
        'photo'       => null,
        'photo_caption' => '',
        'verified'    => false
    ];

    // استخراج JSON از تگ script
    if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
        $json_text = $matches[1];
        $json_data = json_decode($json_text, true);
        if ($json_data && isset($json_data['props']['pageProps']['user'])) {
            $user = $json_data['props']['pageProps']['user'];
            $info['id'] = $user['id'] ?? 'نامشخص';
            $info['title'] = $user['title'] ?? '';
            $info['username'] = $user['nick'] ?? $username;
            $info['bio'] = $user['description'] ?? '';
            $info['photo'] = $user['avatarUrl'] ?? null;
            $info['verified'] = $user['isVerified'] ?? false;
        } elseif ($json_data && isset($json_data['props']['pageProps']['group'])) {
            // اگر گروه یا کانال باشد، false برگردان
            return false;
        }
    }

    // اگر آیدی پیدا نشد، جستجوی عبارات
    if ($info['id'] === 'نامشخص') {
        if (preg_match('/"id":\s*(\d{5,})/', $html, $matches)) {
            $info['id'] = $matches[1];
        } elseif (preg_match('/uid=(\d{5,})/', $html, $matches)) {
            $info['id'] = $matches[1];
        } elseif (preg_match('/"userId":\s*(\d{5,})/', $html, $matches)) {
            $info['id'] = $matches[1];
        }
    }

    // استخراج از متا تگ‌ها
    if (empty($info['title'])) {
        if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $m)) {
            $info['title'] = $m[1];
        }
    }
    if (empty($info['bio'])) {
        if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $m)) {
            $info['bio'] = $m[1];
        }
    }
    if (empty($info['photo'])) {
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $m)) {
            $info['photo'] = $m[1];
        }
    }

    // پالایش عنوان
    $info['title'] = clean_title($info['title']);

    $info['photo_caption'] = generate_photo_caption($info['title'], $info['username'], $info['id']);
    return $info;
}

function fetch_url($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code == 200) ? $html : false;
}

/**
 * دریافت شناسه بات
 */
function get_bot_id() {
    global $API;
    static $bot_id = null;
    if ($bot_id === null) {
        $res = api_request($API . 'getMe', []);
        $data = json_decode($res, true);
        $bot_id = $data['ok'] ? $data['result']['id'] : 0;
    }
    return $bot_id;
}

/**
 * تولید کپشن عکس
 */
function generate_photo_caption($title, $username, $chat_id) {
    return "🖼 *عکس پروفایل*";
}

/**
 * ارسال تمام عکس‌های پروفایل
 */
function send_all_profile_photos($chat_id, $user_id, $identifier) {
    global $API;
    $photos = api_request($API . 'getUserProfilePhotos', ['user_id' => $user_id, 'limit' => 100]);
    $data = json_decode($photos, true);
    if (!$data || !$data['ok'] || empty($data['result']['photos'])) {
        send_message($chat_id, "❌ عکس دیگری یافت نشد.");
        return;
    }

    $total = $data['result']['total_count'];
    $photos_list = $data['result']['photos'];

    send_message($chat_id, "📸 در حال ارسال {$total} عکس...");

    foreach ($photos_list as $sizes) {
        $file_id = end($sizes)['file_id'];
        send_photo($chat_id, $file_id, "📸 عکس پروفایل");
        usleep(500000);
    }

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 بازگشت به اطلاعات', 'callback_data' => 'json:' . $identifier],
                ['text' => '🏠 منو', 'callback_data' => 'start']
            ]
        ]
    ];
    send_message($chat_id, "✅ ارسال عکس‌ها تمام شد.", $keyboard);
}

/**
 * ارسال اطلاعات به صورت JSON (به عنوان پیام)
 */
function send_json_info($chat_id, $info, $add_navigation = true) {
    $json_data = [
        'id'          => $info['id'],
        'name'        => $info['title'],
        'username'    => $info['username'],
        'bio'         => $info['bio'],
        'photo_url'   => $info['photo'],
        'verified'    => $info['verified']
    ];

    $json_string = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $text = "```json\n{$json_string}\n```";

    $keyboard = null;
    if ($add_navigation) {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 جستجوی دوباره', 'callback_data' => 'search'],
                    ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
                ]
            ]
        ];
    }

    send_message($chat_id, $text, $keyboard);
}

/**
 * ارسال اطلاعات کامل با دکمه‌های کپی و چت وب
 */
function send_detailed_info($chat_id, $info) {
    $text = "📋 *اطلاعات کاربر*\n\n"
          . "👤 *کاربر*\n"
          . "🆔 *آیدی عددی:* `{$info['id']}`\n"
          . "📛 *نام:* " . ($info['title'] ?: '—') . "\n";
    if ($info['username']) $text .= "🔗 *نام کاربری:* @{$info['username']}\n";
    if ($info['bio']) $text .= "📝 *بیو/توضیحات:* \n```[متن]\n{$info['bio']}\n```\n";
    if ($info['verified']) $text .= "✅ *حساب تأیید شده:* بله\n";
    $text .= "\n👇 برای کپی هر کدام از موارد زیر، روی دکمه مربوطه کلیک کنید:";

    $keyboard = ['inline_keyboard' => []];

    // کپی آیدی
    $keyboard['inline_keyboard'][] = [
        ['text' => '📋 کپی آیدی عددی', 'copy_text' => ['text' => (string)$info['id']]]
    ];

    // کپی نام
    if ($info['title']) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '📋 کپی نام', 'copy_text' => ['text' => mb_substr($info['title'], 0, 256)]]
        ];
    }

    // کپی نام کاربری
    if ($info['username']) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '📋 کپی نام کاربری', 'copy_text' => ['text' => '@' . $info['username']]]
        ];
    }

    // کپی بیو
    if ($info['bio']) {
        $bio_copy = mb_substr($info['bio'], 0, 256);
        $keyboard['inline_keyboard'][] = [
            ['text' => '📋 کپی بیو', 'copy_text' => ['text' => $bio_copy]]
        ];
    }

    // خلاصه اطلاعات
    $summary = "ID: {$info['id']}\nName: {$info['title']}\n";
    if ($info['username']) $summary .= "Username: @{$info['username']}\n";
    if ($info['bio']) $summary .= "Bio: " . mb_substr($info['bio'], 0, 100);
    $summary_short = mb_substr($summary, 0, 256);
    $keyboard['inline_keyboard'][] = [
        ['text' => '📋 کپی خلاصه', 'copy_text' => ['text' => $summary_short]]
    ];

    // دکمه دریافت JSON
    $keyboard['inline_keyboard'][] = [
        ['text' => '📄 دریافت JSON', 'callback_data' => 'json:' . ($info['username'] ?: $info['id'])]
    ];

    // دکمه مشاهده در بله (با نام کاربری)
    if ($info['username']) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '👤 مشاهده در بله', 'url' => "https://ble.ir/{$info['username']}"]
        ];
    }

    // دکمه چت مستقیم در وب با آیدی عددی
    if (is_numeric($info['id'])) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '💬 چت در وب', 'url' => "https://web.bale.ai/chat?uid={$info['id']}"]
        ];
    }

    // دکمه دریافت تمام عکس‌ها
    if (is_numeric($info['id'])) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '📸 دریافت تمام عکس‌ها', 'callback_data' => 'photos:' . ($info['username'] ?: $info['id'])]
        ];
    }

    // دکمه‌های ناوبری
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔍 جستجوی دوباره', 'callback_data' => 'search'],
        ['text' => '🏠 بازگشت به منو', 'callback_data' => 'start']
    ];

    send_message($chat_id, $text, $keyboard);
}

// ==================== توابع ارسال به API ====================

function send_message($chat_id, $text, $keyboard = null) {
    global $API;
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    if ($keyboard !== null) {
        $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
    $response = api_request($API . 'sendMessage', $payload);
    return json_decode($response, true);
}

function edit_message($chat_id, $message_id, $text, $keyboard = null) {
    global $API;
    $payload = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    if ($keyboard !== null) {
        $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
    return api_request($API . 'editMessageText', $payload);
}

function send_photo($chat_id, $photo, $caption = '') {
    global $API;
    $payload = [
        'chat_id' => $chat_id,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    return api_request($API . 'sendPhoto', $payload);
}

function api_request($url, $params) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);
    return $res;
}
?>