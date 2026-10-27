# Installation and developer workflow

## Requirements

PHP 8.2 or newer; CLI PHP for testing; optional Composer.
No live bank token is required for core unit tests.

## Local working copy

~~~bash
git clone https://github.com/tonsamith128/khrq.git
cd khrq
php tests/lint.php
php tests/run.php
composer validate --strict
composer install
php examples/plain-php/generate.php
~~~

Running Composer at the root installs **development autoloading** for core
and PHP adapter. The Laravel adapter is not required by the root workspace.

## Plain PHP, current development checkout

~~~php
require_once __DIR__ . '/vendor/autoload.php';

$client = \TonSamith\Khqr\Php\KhqrFactory::make();
// $client is a framework-independent \TonSamith\Khqr\Core\Khqr instance.
~~~

A published Composer package will later provide a separate installation route.

## Laravel, development integration

The scaffold lives under `packages/laravel`. It binds the core generator,
but is not yet published to Packagist. Use a Composer path repository in a
local Laravel test application only after installing the matching core package.
See `packages/laravel/config/khqr.php` for non-secret settings. It never
stores bank tokens or triggers live verification by default.

## Production gating

Do not use foundation v0.0.1 as proof of settlement.
Implement and integration-test a bank-specific verifier plus unique storage
and reconciliation before accepting live merchant orders.

