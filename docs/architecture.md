# Architecture — KHQR v0.0.1

## Architectural goal

A small, testable **framework-independent** domain core. PHP and Laravel adapters
depend on the core, not vice versa. The core must never depend on Laravel,
databases, HTTP clients, the hosted website, or a provider's private SDK.

~~~text
Host application (PHP, Laravel, future other languages)
           |
    Framework adapter
           |
       KHQR Core
       |      |
   Codec    Contracts -------- authorized bank / provider adapter (future)
       |
  Optional host-owned persistence / reconciliation (future)
~~~

## Packages

- `packages/core`: strict TLV framing, CRC, account request model, amount formatting, KHQR generator, parsing and verification abstractions.
- `packages/php`: a convenience factory for standalone PHP.
- `packages/laravel`: service container binding, facade and config; no background payment processing.
- `gateway`: future versioned REST/OpenAPI service. No gateway server is shipped.

## Dependency policy

Core may use PHP standard library only. Bank integrations must implement a
contract and must not be placed into a core class constructor by default.
Host applications own bank credentials, settlement state and transaction persistence.

## Composer monorepo release policy

GitHub monorepo root is a development workspace, **not** the installable core
library. Future releases should split each `packages/*` subtree into a distinct
tagged repository or use a monorepo packaging workflow. Do not advertise
`composer require tonsamith/khqr-core` until the package is actually published.

## Versioning

Semantic versioning from 0.0.1 onward. All public APIs are pre-stable and may
change with documented changelog entries. Providers and storage contracts must
be versioned to avoid breaking existing PHP/CodeCanyon host scripts.

## Independent deployment

The SDK must function without calling khqr.tonsamith.com. A future hosted API
is an **optional** separately authenticated product and must be isolated from
individual merchant's secrets and transactional data.

