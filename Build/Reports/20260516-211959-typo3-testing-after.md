# TYPO3 testing report after changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-testing

Changes made:
- Added `Build/Scripts/runTests.sh` with `lint`, `phpstan`, `unit`, and `ci`
  suites.
- Added GitHub Actions on PHP 8.2, 8.3, 8.4, and 8.5.
- Updated unit tests for TYPO3 `RequestFactory` and v14 routing attributes.
- Fixed `PaymentVerifier::settle()` to satisfy the existing failure test.

Local verification limitation:
- The local PHP CLI is 8.1.34, while this extension now requires PHP 8.2+.
  Runtime linting, PHPStan, and PHPUnit must run in GitHub Actions or a PHP
  8.2+ environment.

