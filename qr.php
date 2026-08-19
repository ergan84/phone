<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    http_response_code(403);
    exit;
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/vcard.php';

$i = isset($_GET['i']) ? (int)$_GET['i'] : -1;

$result_entries = null;
try {
    $redis = new Redis();
    if ($redis->connect($config['redis_host'], $config['redis_port'], 1.0)) {
        $cached = $redis->get('phonebook:directory');
        if ($cached !== false) {
            $result_entries = unserialize($cached, ['allow_classes' => false]);
        }
    }
} catch (Throwable $e) {
    $result_entries = null;
}

if ($result_entries === null || $i < 0 || $i >= ($result_entries['count'] ?? 0)) {
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

header('Content-Type: image/svg+xml');
header('Cache-Control: private, max-age=300');
echo buildQrSvg($vcard, 4);
