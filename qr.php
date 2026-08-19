<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    http_response_code(403);
    exit;
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/vcard.php';

$i = isset($_GET['i']) ? (int)$_GET['i'] : -1;
if ($i < 0) {
    http_response_code(404);
    exit;
}

$redis = null;
try {
    $r = new Redis();
    if ($r->connect($config['redis_host'], $config['redis_port'], 1.0)) {
        $redis = $r;
    }
} catch (Throwable $e) {
    $redis = null;
}

$qrCacheKey = 'phonebook:qr:' . $i;
$svg = null;
if ($redis) {
    $cachedSvg = $redis->get($qrCacheKey);
    if ($cachedSvg !== false) {
        $svg = $cachedSvg;
    }
}

// Готового QR в кэше нет — читаем справочник и строим заново.
// SVG кэшируем отдельно от справочника: он не меняется, пока не истечёт
// тот же TTL, а собирать его (перебор размеров QR) не бесплатно по CPU.
if ($svg === null) {
    $result_entries = null;
    if ($redis) {
        $cached = $redis->get('phonebook:directory');
        if ($cached !== false) {
            $result_entries = unserialize($cached, ['allow_classes' => false]);
        }
    }

    if ($result_entries === null || $i >= ($result_entries['count'] ?? 0)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'QR временно недоступен, обновите страницу справочника';
        exit;
    }

    $entry = $result_entries[$i];
    $vcard = buildVCard(
        $entry['cn'][0] ?? '',
        $entry['title'][0] ?? '',
        $entry['company'][0] ?? '',
        $entry['department'][0] ?? '',
        formatMobilePhone($entry['mobile'][0] ?? ''),
        $entry['mail'][0] ?? ''
    );

    $svg = buildQrSvg($vcard, 4);

    if ($redis && $svg !== '') {
        $redis->setex($qrCacheKey, $config['redis_ttl'], $svg);
    }
}

header('Content-Type: image/svg+xml');
header('Cache-Control: private, max-age=300');
echo $svg;
