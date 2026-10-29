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
OR a local private PNG/JPEG QR image via zbarimg or optional OpenCV.

Security requirements: absolute file path, 0600 permissions, containing
private directory 0700. Never paste a raw bank QR in chat, GitHub, CLI
arguments or a publicly served directory.

Text preflight uses only core PHP. Image preflight uses local zbarimg
(zbar-tools), falling back to optional private qr-venv OpenCV 4.13, then system
/usr/bin/python3 with cv2 if available.
Neither decoder uses online QR services.

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


    if (!function_exists('exif_imagetype')) {
        qrFail('PHP_EXIF_EXTENSION_REQUIRED');
    }
    $type = @exif_imagetype($file);
    if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
        qrFail('PNG_OR_JPEG_REQUIRED');
    }

    // Prefer lightweight zbar, then the optional local Python/OpenCV decoder.
    // Both may see the raw bank QR, so redirect ALL decoder output into pipes.
    // Nothing from either decoder is printed, logged, or sent over a network.
    $scanners = [];
    if (is_executable('/usr/bin/zbarimg')) {
        $scanners['ZBAR'] = ['/usr/bin/zbarimg', '--quiet', '--raw', $file];
    }
    // Prefer opt-in private modern OpenCV without modifying system Python.
    $privatePython = dirname($file) . '/qr-venv/bin/python3';
    if (is_executable($privatePython) &&
        is_executable('/usr/bin/timeout') &&
        is_file(__DIR__ . '/qr-opencv-decode.py')) {
        $scanners['OPENCV_ISOLATED'] = [
            '/usr/bin/timeout', '--signal=TERM', '40s',
            $privatePython, __DIR__ . '/qr-opencv-decode.py', $file,
        ];
    }

    if (is_executable('/usr/bin/python3') &&
        is_executable('/usr/bin/timeout') &&
        is_file(__DIR__ . '/qr-opencv-decode.py')) {
        $scanners['OPENCV'] = [
            '/usr/bin/timeout', '--signal=TERM', '40s',
            '/usr/bin/python3', __DIR__ . '/qr-opencv-decode.py', $file,
        ];
    }

    $payload = null;
    $decoder = null;
    foreach ($scanners as $name => $command) {
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($file),
        );
        if (!is_resource($process)) {
            continue;
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1], 2049);
        // Errors can contain private paths or payment details; discard.
        stream_get_contents($pipes[2], 2049);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit === 0 && is_string($output) &&
            $output !== '' && strlen($output) <= 2048) {
            $payload = rtrim($output, "\r\n");
            $decoder = $name;
            break;
        }
    }

    if ($payload === null) {
        qrFail('LOCAL_SCANNER_FAILED');
    }
}

try {
    $result = (new BankQrCompatibility())->inspect($payload);
} catch (Throwable) {
    qrFail('UNRECOGNIZED_OR_UNSUPPORTED_KHQR');
} finally {
    unset($payload);
}

echo "QR_PREFLIGHT=COMPLETED_OFFLINE\n";
echo "LOCAL_DECODER=" . ($decoder ?? "TEXT_FILE") . PHP_EOL;
foreach ($result as $key => $value) {
    echo strtoupper($key) . '=' . $value . PHP_EOL;
}

