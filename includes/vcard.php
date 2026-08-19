<?php
// Общие хелперы для phone.php и qr.php: форматирование телефона, сборка
// vCard и рендер QR-кода. Вынесены отдельно, чтобы не дублировать логику.

// Приводим мобильный к единому формату "+7 XXX XXXXXXX"; если номер не
// казахстанский/российский (не 10-11 цифр с кодом 7/8), оставляем как есть.
function formatMobilePhone($raw) {
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $digits = '7' . substr($digits, 1);
    } elseif (strlen($digits) === 10) {
        $digits = '7' . $digits;
    }
    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        return $raw;
    }
    return '+7 ' . substr($digits, 1, 3) . ' ' . substr($digits, 4);
}

// Экранирование значения под формат vCard (RFC 6350).
function vcardEscape($value) {
    return str_replace(
        ["\\", ",", ";", "\r\n", "\n"],
        ["\\\\", "\\,", "\\;", "\\n", "\\n"],
        (string)$value
    );
}

function buildVCard($cn, $title, $company, $department, $mobile, $mail) {
    $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . vcardEscape($cn)];
    if ($title !== '') {
        $lines[] = 'TITLE:' . vcardEscape($title);
    }
    if ($company !== '' || $department !== '') {
        $lines[] = 'ORG:' . vcardEscape($company) . ($department !== '' ? ';' . vcardEscape($department) : '');
    }
    if ($mobile !== '') {
        $lines[] = 'TEL;TYPE=CELL:' . vcardEscape($mobile);
    }
    if ($mail !== '') {
        $lines[] = 'EMAIL:' . vcardEscape($mail);
    }
    $lines[] = 'END:VCARD';
    return implode("\n", $lines);
}

// Рендер QR в SVG. Библиотека kazuhikoarase/qrcode-generator не подбирает
// размер (typeNumber) автоматически и падает с фатальной ошибкой
// (trigger_error E_USER_ERROR), если данные не влезают в текущий размер —
// поэтому подбираем размер сами, начиная с малого и увеличивая при неудаче.
function buildQrSvg($text, $moduleSize = 4) {
    require_once __DIR__ . '/../vendor/qrcode-generator/qrcode.php';

    $prevHandler = set_error_handler(function ($errno, $errstr) {
        throw new RuntimeException($errstr);
    }, E_USER_ERROR);

    $qr = null;
    try {
        for ($typeNumber = 1; $typeNumber <= 40; $typeNumber++) {
            $candidate = new QRCode();
            $candidate->setTypeNumber($typeNumber);
            $candidate->setErrorCorrectLevel(QR_ERROR_CORRECT_LEVEL_L);
            $candidate->addData($text);
            try {
                $candidate->make();
                $qr = $candidate;
                break;
            } catch (RuntimeException $e) {
                // данные не влезли — пробуем следующий размер
            }
        }
    } finally {
        set_error_handler($prevHandler);
    }

    if ($qr === null) {
        return '';
    }

    ob_start();
    $qr->printSVG($moduleSize);
    return ob_get_clean();
}
