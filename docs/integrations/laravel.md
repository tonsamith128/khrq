# Laravel adapter — source scaffold

Package source: `packages/laravel`. It is not published to Packagist.
For local Laravel testing, configure Composer path repositories for both
`packages/core` and `packages/laravel`, then register via auto-discovery.

The service provider binds `TonSamith\Khqr\Core\Khqr`; the facade is
`TonSamith\Khqr\Laravel\Facades\Khqr`.

Publish default settings:

~~~bash
php artisan vendor:publish --tag=khqr-config
~~~

~~~php
use TonSamith\Khqr\Core\PaymentRequest;
use TonSamith\Khqr\Laravel\Facades\Khqr;

$result = Khqr::generate(new PaymentRequest(
    accountId: 'example@bank',
    merchantName: 'Example Shop',
    merchantCity: 'Phnom Penh',
    amountMinor: 10000,
    reference: 'ORDER-1001',
));

// Payload generation is not settlement confirmation.
~~~

No bank tokens, migrations, queues, callbacks, or settlement verification
are shipped in this foundation adapter.

