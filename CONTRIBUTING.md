# Contributing

KHQR welcomes reviewed improvements to the framework-neutral foundation.

1. Open an issue to discuss externally visible protocol or public API changes.
2. Fork the repository and work on a focused branch.
3. Follow PHP 8.2+ syntax, PSR-4, PSR-12 and typed values.
4. Run `php tests/lint.php` and `php tests/run.php`.
5. Add regression tests, including malformed payloads and adversarial values.
6. Never commit credentials, personal account data, generated production QR codes, or private banking specifications.
7. Submit a pull request and summarize the security and compatibility impact.

Code remains covered by the repository MIT license. Vendor/bank-specific behavior requires appropriate rights and independent verification.

