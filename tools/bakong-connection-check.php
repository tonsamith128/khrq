<?php

declare(strict_types=1);

/**
 * Safe, operator-triggered Bakong API connection smoke.
 *
 * - No bank calls by default (dry-run).
 * - Reads secret from 0600 protected file only, never argv.
 * - Requires two explicit operator acknowledgements before any network call.
 * - NEVER persists payment state, prints bearer secrets or dumps responses.
 * - SIT and production tokens may be separate. Do not assume interchangeability.
 */
use TonSamith\Khqr\Bakong\BakongMd5Verifier;
use TonSamith\Khqr\Bakong\Http\AuthorizedBakongTransport;
use TonSamith\Khqr\Bakong\Http\BakongHttpOptions;
use TonSamith\Khqr\Bakong\Http\BakongTransportException;
use TonSamith\Khqr\Bakong\Http\CurlHttpClient;
use TonSamith\Khqr\Bakong\Http\FileDailyQuota;
use TonSamith\Khqr\Bakong\Http\PrivateTokenFile;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloader)) {
    require_once $autoloader;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'TonSamith\\Khqr\\Core\\' => dirname(__DIR__) . '/packages/core/src/',
            'TonSamith\\Khqr\\Bakong\\' => dirname(__DIR__) . '/packages/bakong/src/',
        ];

        foreach ($prefixes as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $path = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
                break;
            }
        }
    });
}

$help = <<<'HELP'
KHQR Bakong connection check (no payments or automatic settlement)

Offline only:
  php tools/bakong-connection-check.php --dry-run
  php tools/bakong-connection-check.php --help

Authorized SIT test (consumes ONE request):
  php tools/bakong-connection-check.php --execute --confirm-authorized-use \
    --environment=sit --token-file=/PRIVATE/khqr/bakong.token \
    --quota-state=/PRIVATE/khqr/quota-sit.json --md5=32_HEX_DIGITS

Authorized production-endpoint lookup (consumes ONE request, no settlement):
  Add --acknowledge-production to the same command and set
    --environment=production --quota-state=/PRIVATE/khqr/quota-production.json

The token MUST be kept outside the web root in a 0600 file inside a 0700
directory. Never paste the token into ChatGPT, GitHub, shell command arguments
or an .env file in a public folder. Production API tests must be explicitly
authorized by the provider; the email token alone does not prove such approval.

Output contains only coarse response status, never token, MD5, account data
or transaction hashes. Test does not change database/order payment status.
HELP;

$options = [
    'execute' => false,
    'confirm-authorized-use' => false,
    'acknowledge-production' => false,
    'environment' => 'sit',
    'md5' => null,
    'token-file' => null,
    'quota-state' => null,
];
$allowed = array_keys($options);

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo $help . PHP_EOL;
        exit(0);
    }
    if ($argument === '--dry-run') {
        echo "MODE=DRY_RUN\nNETWORK=DISABLED\nSETTLEMENT=DISABLED\n";
        exit(0);
    }

    if (!str_starts_with($argument, '--')) {
        fwrite(STDERR, "ERROR=INVALID_ARGUMENT\n");
        exit(2);
    }

    $split = explode('=', substr($argument, 2), 2);
    $name = $split[0];
    if (!in_array($name, $allowed, true)) {
        fwrite(STDERR, "ERROR=UNKNOWN_OPTION\n");
        exit(2);
    }

    if (in_array($name, ['execute', 'confirm-authorized-use', 'acknowledge-production'], true)) {
        if (count($split) !== 1) {
            fwrite(STDERR, "ERROR=INVALID_FLAG\n");
            exit(2);
        }
        $options[$name] = true;
    } else {
        if (count($split) !== 2 || $split[1] === '') {
            fwrite(STDERR, "ERROR=MISSING_OPTION_VALUE\n");
            exit(2);
        }
        $options[$name] = $split[1];
    }
}

if (!$options['execute']) {
    echo "MODE=DRY_RUN\nNETWORK=DISABLED\nSETTLEMENT=DISABLED\n";
    exit(0);
}

// Fail before secret access or network IO unless deliberate opt-in is explicit.
if (!$options['confirm-authorized-use'] ||
    !in_array($options['environment'], ['sit', 'production'], true) ||
    ($options['environment'] === 'production' && !$options['acknowledge-production']) ||
    !is_string($options['md5']) ||
    preg_match('/^[0-9a-f]{32}$/iD', $options['md5']) !== 1 ||
    !is_string($options['token-file']) ||
    !is_string($options['quota-state'])) {
    fwrite(STDERR, "ERROR=PRECHECK_NOT_SATISFIED\n");
    exit(2);
}

$host = $options['environment'] === 'production'
    ? 'api-bakong.nbc.gov.kh'
    : 'sit-api-bakong.nbc.gov.kh';

try {
    $secret = new PrivateTokenFile($options['token-file']);
    $quota = new FileDailyQuota($options['quota-state'], 100);
    $transport = new AuthorizedBakongTransport(
        new BakongHttpOptions(
            baseUrl: 'https://' . $host,
            networkEnabled: true,
            allowedHosts: [$host],
        ),
        new CurlHttpClient(),
        $quota,
        static fn (): string => $secret->get(),
    );
    $result = (new BakongMd5Verifier($transport))->checkByMd5($options['md5']);

    echo "NETWORK_CHECK=HTTP_200_RECEIVED\n";
    echo 'PROVIDER_OBSERVATION=' . $result->status->value . PHP_EOL;
    echo "SETTLEMENT=DISABLED\n";
    exit(0);
} catch (BakongTransportException $error) {
    // Only controlled, redacted exception messages from our network classes.
    fwrite(STDERR, "NETWORK_CHECK=FAILED\nERROR=" . $error->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    // No raw exception/trace output: may expose secret path or provider data.
    fwrite(STDERR, "NETWORK_CHECK=FAILED\nERROR=UNEXPECTED_CHECK_FAILURE\n");
    exit(1);
}

