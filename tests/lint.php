<?php
declare(strict_types=1);

/** PHP parser checks without external dependencies. */
$root = dirname(__DIR__);
$count = 0;
$failure = false;

foreach (['packages', 'tests', 'examples'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        try {
            token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
            $count++;
        } catch (ParseError $exception) {
            fwrite(STDERR, $file->getPathname() . ': ' . $exception->getMessage() . PHP_EOL);
            $failure = true;
        }
    }
}

echo 'Parsed ' . $count . ' PHP files.' . PHP_EOL;
exit($failure ? 1 : 0);

