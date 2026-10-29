<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

/**
 * Read a server-only bearer token from a protected local file.
 *
 * A token is NEVER accepted as an HTTP argument, URL parameter, CLI option,
 * log field or GitHub Actions environment variable.
 */
final readonly class PrivateTokenFile
{
    public function __construct(private string $path)
    {
    }

    public function get(): string
    {
        if ($this->path === '' || !str_starts_with($this->path, '/') ||
            is_link($this->path) || !is_file($this->path)) {
            throw new BakongTransportException('Private token file is missing or insecure.');
        }

        $dir = dirname($this->path);
        if (is_link($dir) || !is_dir($dir) ||
            (fileperms($dir) & 0077) !== 0) {
            throw new BakongTransportException('Private token directory must not be accessible by other users.');
        }

        $before = @lstat($this->path);
        if ($before === false || ($before['mode'] & 0170000) !== 0100000 ||
            ($before['mode'] & 0077) !== 0 || $before['size'] > 4096 ||
            $before['nlink'] !== 1) {
            throw new BakongTransportException('Private token file permissions or size are invalid.');
        }

        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            throw new BakongTransportException('Cannot read private provider token file.');
        }

        try {
            $opened = fstat($handle);
            if ($opened === false ||
                $opened['ino'] !== $before['ino'] ||
                $opened['dev'] !== $before['dev'] ||
                ($opened['mode'] & 0170000) !== 0100000 ||
                ($opened['mode'] & 0077) !== 0) {
                throw new BakongTransportException('Private token file changed or has unsafe permissions.');
            }

            $raw = stream_get_contents($handle, 4097);
            if (!is_string($raw) || strlen($raw) > 4096) {
                throw new BakongTransportException('Private provider token file is invalid.');
            }
        } finally {
            fclose($handle);
        }

        $token = rtrim($raw, "\r\n");

        if (strlen($token) < 16 || strlen($token) > 4096 ||
            preg_match('/^[\x21-\x7E]+$/D', $token) !== 1) {
            throw new BakongTransportException('Private provider token format is invalid.');
        }

        return $token;
    }
}

