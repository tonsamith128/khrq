<?php

declare(strict_types=1);

/**
 * CLI-only, strictly OFFLINE preflight for a bank-issued QR payload or image.
 * No token access, network requests, payment initiation or database writes.
 * Private file contents, receiving identifiers and MD5 are never printed.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/tests/bootstrap.php';

use TonSamith\Khqr\Core\BankQrCompatibility;

$help = <<<'HELP'
KHQR offline compatibility preflight — NO BANK CONNECTION

  php tools/bank-qr-offline-preflight.php --help
  php tools/bank-qr-offline-preflight.php --text-file=/root/.config/khqr/aba.txt
  php tools/bank-qr-offline-preflight.php --image-file=/root/.config/khqr/aba.png

Accepts a local private text file containing the raw, decoded QR payload,
OR a local private PNG/JPEG QR image when /usr/bin/zbarimg is installed.

Security requirements: absolute file path, 0600 permissions, containing
private directory 0700. Never paste a raw bank QR in chat, GitHub, CLI
arguments or a publicly served directory.

Text preflight uses only core PHP. Image preflight uses the local zbarimg
scanner (zbar-tools), never an online QR decoder.

Results reveal only QR structure, template, currency, amount/reference
presence and SDK compatibility — NEVER account identifiers or payment data.
KHQR validity is NOT proof the recipient or account is trustworthy.
HELP;

function qrFail(string $code): never
{
    fwrite(STDERR, "QR_PREFLIGHT=BLOCKED\nERROR=$code\n");
    exit(1);
}

/** @return string Secure local file contents */
function qrReadPrivateFile(string $path, int $limit): string
{
    if ($path === '' || !str_starts_with($path, DIRECTORY_SEPARATOR) ||
        is_link($path) || !is_file($path)) {
        qrFail('PRIVATE_FILE_REQUIRED');
    }

    $directory = dirname($path);
    if (is_link($directory) || !is_dir($directory) ||
        ((fileperms($directory) & 0077) !== 0)) {
        qrFail('PRIVATE_DIRECTORY_PERMISSIONS');
    }

    $before = @lstat($path);
    if ($before === false || ($before['mode'] & 0170000) !== 0100000 ||
        ($before['mode'] & 0077) !== 0 || $before['nlink'] !== 1 ||
        $before['size'] < 1 || $before['size'] > $limit) {
        qrFail('PRIVATE_FILE_PERMISSIONS_OR_SIZE');
    }

    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        qrFail('PRIVATE_FILE_UNREADABLE');
    }

    try {
        $opened = @fstat($handle);
        if ($opened === false ||
            $opened['ino'] !== $before['ino'] ||
            $opened['dev'] !== $before['dev'] ||
            ($opened['mode'] & 0170000) !== 0100000 ||
            ($opened['mode'] & 0077) !== 0) {
            qrFail('PRIVATE_FILE_CHANGED');
        }
        $data = stream_get_contents($handle, $limit + 1);
        if (!is_string($data) || strlen($data) > $limit) {
            qrFail('PRIVATE_FILE_OVERSIZED');
        }
        return $data;
    } finally {
        fclose($handle);
    }
}

if (count($argv) === 2 && $argv[1] === '--help') {
    echo $help . PHP_EOL;
    exit(0);
}

if (count($argv) !== 2 ||
    preg_match('/^--(text-file|image-file)=(\/.+)$/D', $argv[1], $match) !== 1) {
    qrFail('USAGE_REQUIRES_ONE_PRIVATE_FILE');
}

$mode = $match[1];
$file = $match[2];
if ($mode === 'text-file') {
    $payload = rtrim(qrReadPrivateFile($file, 2048), "\r\n");
} else {
    // Validate the input as a private file BEFORE passing it to a local decoder.
    $bytes = qrReadPrivateFile($file, 2097152);
    unset($bytes);

    if (!is_file('/usr/bin/zbarimg') || !is_executable('/usr/bin/zbarimg')) {
        qrFail('LOCAL_ZBARIMG_NOT_INSTALLED');
    }
    if (!function_exists('exif_imagetype')) {
        qrFail('PHP_EXIF_EXTENSION_REQUIRED');
    }
    $type = @exif_imagetype($file);
    if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
        qrFail('PNG_OR_JPEG_REQUIRED');
    }

    $pipes = [];
    $process = @proc_open(
        ['/usr/bin/zbarimg', '--quiet', '--raw', $file],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname($file),
    );

    if (!is_resource($process)) {
        qrFail('LOCAL_SCANNER_FAILED');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1], 2049);
    // Ignore diagnostic text (which could contain the QR or account).
    stream_get_contents($pipes[2], 2049);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || !is_string($output) || strlen($output) > 2048) {
        qrFail('LOCAL_SCANNER_FAILED');
    }
    $payload = rtrim($output, "\r\n");
}

try {
    $result = (new BankQrCompatibility())->inspect($payload);
} catch (Throwable) {
    qrFail('UNRECOGNIZED_OR_UNSUPPORTED_KHQR');
} finally {
    unset($payload);
}

echo "QR_PREFLIGHT=COMPLETED_OFFLINE\n";
foreach ($result as $key => $value) {
    echo strtoupper($key) . '=' . $value . PHP_EOL;
}

