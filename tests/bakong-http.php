<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TonSamith\Khqr\Bakong\BakongMd5Verifier;
use TonSamith\Khqr\Bakong\Http\AuthorizedBakongTransport;
use TonSamith\Khqr\Bakong\Http\BakongHttpOptions;
use TonSamith\Khqr\Bakong\Http\BakongTransportException;
use TonSamith\Khqr\Bakong\Http\FileDailyQuota;
use TonSamith\Khqr\Bakong\Http\HttpClientInterface;
use TonSamith\Khqr\Bakong\Http\HttpResponse;
use TonSamith\Khqr\Core\TransactionStatus;

$passes = 0;
$failures = 0;

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function shouldFail(callable $callback, string $expectedClass): string
{
    try {
        $callback();
    } catch (Throwable $error) {
        verify($error instanceof $expectedClass, 'Unexpected exception type: ' . get_class($error));
        return $error->getMessage();
    }

    throw new RuntimeException('Expected exception was not thrown.');
}

function scenario(string $name, callable $callback): void
{
    global $passes, $failures;
    try {
        $callback();
        $passes++;
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $error->getMessage() . PHP_EOL);
    }
}

final class FakeBakongHttp implements HttpClientInterface
{
    public array $calls = [];
    public int $statusCode = 200;
    public string $contentType = 'application/json; charset=utf-8';
    public string $body = '{"responseCode":1,"errorCode":1,"data":null}';

    public function postJson(
        string $url,
        string $bearerToken,
        string $jsonBody,
        int $connectTimeoutSeconds,
        int $totalTimeoutSeconds,
    ): HttpResponse {
        $this->calls[] = [$url, $bearerToken, $jsonBody, $connectTimeoutSeconds, $totalTimeoutSeconds];

        return new HttpResponse($this->statusCode, $this->contentType, $this->body);
    }
}

/** @return array{0:FileDailyQuota,1:string,2:string} */
function newQuota(int $limit = 100): array
{
    $dir = sys_get_temp_dir() . '/khqr-http-test-' . bin2hex(random_bytes(8));
    verify(mkdir($dir, 0700), 'Create private quota directory');
    $file = $dir . '/requests.json';

    return [new FileDailyQuota($file, $limit), $file, $dir];
}

function cleanQuota(string $file, string $dir): void
{
    if (is_file($file)) {
        unlink($file);
    }
    rmdir($dir);
}

scenario('Network disabled by default; never calls HTTP', static function (): void {
    [$quota, $file, $dir] = newQuota();
    try {
        $http = new FakeBakongHttp();
        $transport = new AuthorizedBakongTransport(
            new BakongHttpOptions(), $http, $quota, static fn (): string => str_repeat('X', 30),
        );
        shouldFail(static fn () => $transport->checkTransactionByMd5(str_repeat('a', 32)), BakongTransportException::class);
        verify(count($http->calls) === 0, 'Network was called while disabled');
        verify(!is_file($file), 'Disabled network must not create quota state');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Origin validation rejects localhost, insecure, ports and spoofed hosts', static function (): void {
    foreach ([
        'http://api-bakong.nbc.gov.kh',
        'https://localhost',
        'https://api-bakong.nbc.gov.kh.evil.com',
        'https://api-bakong.nbc.gov.kh:443',
        'https://api-bakong.nbc.gov.kh/v1/other',
        'https://user:secret@api-bakong.nbc.gov.kh',
        'https://api-bakong.nbc.gov.kh?url=evil',
        'https://127.0.0.1',
    ] as $origin) {
        shouldFail(static fn () => new BakongHttpOptions(
            baseUrl: $origin, networkEnabled: true,
        ), InvalidArgumentException::class);
    }
});

scenario('Authorized transport POST body and headers contract', static function (): void {
    [$quota, $file, $dir] = newQuota();
    try {
        $http = new FakeBakongHttp();
        $options = new BakongHttpOptions(networkEnabled: true);
        $token = 'not-a-real-bank-token-secret-12345';
        $transport = new AuthorizedBakongTransport(
            $options, $http, $quota, static fn (): string => $token,
        );
        $raw = $transport->checkTransactionByMd5(str_repeat('A', 32));

        verify(json_decode($raw, true)['responseCode'] === 1, 'Raw response preserved for mapper');
        verify(count($http->calls) === 1, 'Exactly one request');
        [$url, $sentToken, $body, $connect, $total] = $http->calls[0];
        verify($url === 'https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5', 'Endpoint');
        verify($sentToken === $token, 'Server-held token');
        verify($body === '{"md5":"' . str_repeat('a', 32) . '"}', 'Lowercased JSON MD5');
        verify($connect === 3 && $total === 10, 'Bounded timeouts');
        verify(!str_contains($raw, $token), 'Token must not appear in response');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Fail closed on empty token, invalid MD5, redirects and HTTP errors', static function (): void {
    [$quota, $file, $dir] = newQuota();
    try {
        $http = new FakeBakongHttp();
        $options = new BakongHttpOptions(networkEnabled: true);
        $badToken = new AuthorizedBakongTransport(
            $options, $http, $quota, static fn (): string => '',
        );
        shouldFail(static fn () => $badToken->checkTransactionByMd5(str_repeat('a', 32)), BakongTransportException::class);
        verify(!is_file($file), 'Missing token must not consume quota');

        $transport = new AuthorizedBakongTransport(
            $options, $http, $quota, static fn (): string => str_repeat('T', 32),
        );

        shouldFail(static fn () => $transport->checkTransactionByMd5('not-a-valid-lookup'), InvalidArgumentException::class);
        foreach ([301, 302, 401, 403, 404, 429, 500] as $status) {
            $http->statusCode = $status;
            $error = shouldFail(
                static fn () => $transport->checkTransactionByMd5(str_repeat('b', 32)),
                BakongTransportException::class,
            );
            verify(!str_contains($error, str_repeat('T', 32)), 'Never leak token in errors');
        }
        verify(count($http->calls) === 7, 'No automatic retries');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Atomic daily budget rejects calls beyond maximum', static function (): void {
    [$quota, $file, $dir] = newQuota(2);
    try {
        $http = new FakeBakongHttp();
        $transport = new AuthorizedBakongTransport(
            new BakongHttpOptions(networkEnabled: true),
            $http, $quota, static fn (): string => str_repeat('T', 32),
        );
        $md5 = str_repeat('c', 32);
        $transport->checkTransactionByMd5($md5);
        $transport->checkTransactionByMd5($md5);
        shouldFail(static fn () => $transport->checkTransactionByMd5($md5), BakongTransportException::class);
        verify(count($http->calls) === 2, 'Daily budget prevents third request');
        $record = json_decode((string) file_get_contents($file), true);
        verify($record['count'] === 2, 'Persisted count');
        verify($record['day'] === gmdate('Y-m-d'), 'Uses UTC date');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Refuse corrupted state and quota directory symlinks', static function (): void {
    [$quota, $file, $dir] = newQuota(2);
    try {
        file_put_contents($file, '{"count":"unsafe","day":"2026-10-28"}');
        shouldFail(static fn () => $quota->consume(), BakongTransportException::class);
        verify(str_contains((string) file_get_contents($file), '"unsafe"'), 'Corrupt state remains for audit');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Provider mapper + HTTPS transport is pending, never a fake receipt', static function (): void {
    [$quota, $file, $dir] = newQuota();
    try {
        $http = new FakeBakongHttp();
        $transport = new AuthorizedBakongTransport(
            new BakongHttpOptions(networkEnabled: true),
            $http, $quota, static fn (): string => str_repeat('T', 32),
        );
        $lookup = (new BakongMd5Verifier($transport))->checkByMd5(str_repeat('d', 32));
        verify($lookup->status === TransactionStatus::PENDING, 'Pending lookup is not paid');
    } finally {
        cleanQuota($file, $dir);
    }
});

scenario('Reject invalid provider content type, size and JSON', static function (): void {
    [$quota, $file, $dir] = newQuota(3);
    try {
        $http = new FakeBakongHttp();
        $transport = new AuthorizedBakongTransport(
            new BakongHttpOptions(networkEnabled: true),
            $http, $quota, static fn (): string => str_repeat('T', 32),
        );

        $http->contentType = 'text/html';
        shouldFail(static fn () => $transport->checkTransactionByMd5(str_repeat('e', 32)), BakongTransportException::class);
        $http->contentType = 'application/json';
        $http->body = str_repeat('x', 32769);
        shouldFail(static fn () => $transport->checkTransactionByMd5(str_repeat('e', 32)), BakongTransportException::class);
        $http->body = 'not valid json';
        $observation = (new BakongMd5Verifier($transport))->checkByMd5(str_repeat('e', 32));
        verify($observation->status === TransactionStatus::REVIEW, 'Invalid JSON cannot settle orders');
    } finally {
        cleanQuota($file, $dir);
    }
});

echo PHP_EOL . sprintf('Transport tests: %d passed, %d failed', $passes, $failures) . PHP_EOL;
exit($failures === 0 ? 0 : 1);

