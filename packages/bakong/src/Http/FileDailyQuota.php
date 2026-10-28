<?php

declare(strict_types=1);

namespace TonSamith\Khqr\Bakong\Http;

/**
 * Simple process-shared UTC-day request budget for PHP/shared hosting.
 * Consume BEFORE network IO (uncertain requests still count).
 *
 * The caller must pre-create a PRIVATE (0700) non-symlink directory
 * OUTSIDE the web root. Tests use a random disposable directory.
 */
final readonly class FileDailyQuota
{
    public function __construct(
        private string $statePath,
        private int $dailyLimit = 100,
    ) {
        $parent = dirname($statePath);
        if ($statePath === '' || !str_starts_with($statePath, DIRECTORY_SEPARATOR) ||
            $dailyLimit < 1 || $dailyLimit > 100 ||
            is_link($parent) || !is_dir($parent) ||
            (fileperms($parent) & 0077) !== 0 ||
            is_link($statePath) || (file_exists($statePath) && !is_file($statePath))) {
            throw new BakongTransportException('Private quota state path is unavailable or insecure.');
        }
    }

    public function consume(): void
    {
        if (is_link($this->statePath)) {
            throw new BakongTransportException('Refusing to follow quota state symlink.');
        }

        $handle = @fopen($this->statePath, 'c+');
        if ($handle === false) {
            throw new BakongTransportException('Cannot open private request quota state.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new BakongTransportException('Cannot lock request quota state.');
            }

            $metadata = fstat($handle);
            if ($metadata === false || ($metadata['mode'] & 0170000) !== 0100000) {
                throw new BakongTransportException('Quota state must be a regular file.');
            }

            // chmod is safe after opening and locking the regular file.
            if (!@chmod($this->statePath, 0600)) {
                throw new BakongTransportException('Cannot secure quota state file.');
            }

            rewind($handle);
            $content = stream_get_contents($handle, 1025);
            if (!is_string($content) || strlen($content) > 1024) {
                throw new BakongTransportException('Request quota state is oversized.');
            }

            $today = gmdate('Y-m-d');
            $count = 0;
            if ($content !== '') {
                $state = json_decode($content, true);
                if (!is_array($state) ||
                    !is_string($state['day'] ?? null) ||
                    !is_int($state['count'] ?? null) ||
                    $state['count'] < 0) {
                    throw new BakongTransportException('Request quota state is corrupt.');
                }

                if ($state['day'] === $today) {
                    $count = $state['count'];
                }
            }

            if ($count >= $this->dailyLimit) {
                throw new BakongTransportException('Provider daily request budget exhausted.');
            }

            $next = json_encode(['day' => $today, 'count' => $count + 1], JSON_THROW_ON_ERROR);

            if (!ftruncate($handle, 0) || !rewind($handle) ||
                fwrite($handle, $next) !== strlen($next) || !fflush($handle)) {
                throw new BakongTransportException('Unable to persist the request budget.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

