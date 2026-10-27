<?php
declare(strict_types=1);

// Offline demonstration only: no payment transfer or settlement verification.
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    require_once dirname(__DIR__, 2) . '/tests/bootstrap.php';
}

use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Php\KhqrFactory;

$qr = KhqrFactory::make()->generate(new PaymentRequest(
    accountId: 'example@bank',
    merchantName: 'Example Shop',
    merchantCity: 'Phnom Penh',
    currency: 'KHR',
    amountMinor: 10000,
    reference: 'DEMO-ORDER-1',
));

echo 'KHQR payload: ' . $qr->payload . PHP_EOL;
echo 'Lookup MD5 (not proof of payment): ' . $qr->md5 . PHP_EOL;
echo 'No settlement verification was performed.' . PHP_EOL;

