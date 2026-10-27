# Plain PHP adapter

Try the bundled offline example:

~~~bash
php examples/plain-php/generate.php
~~~

~~~php
use TonSamith\Khqr\Php\KhqrFactory;
use TonSamith\Khqr\Core\PaymentRequest;

$client = KhqrFactory::make();
$qr = $client->generate(new PaymentRequest(
    accountId: 'example@bank',
    merchantName: 'Example Shop',
    merchantCity: 'Battambang',
    amountMinor: 5000
));
echo $qr->payload;
~~~

No Laravel, database, hosted gateway, or payment processor is required
to construct payloads. Authorized verification is still required before
marking any order paid.

