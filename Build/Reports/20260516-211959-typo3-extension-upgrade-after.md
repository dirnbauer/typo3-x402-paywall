# TYPO3 extension upgrade report after changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-extension-upgrade

Changes made:
- Dropped TYPO3 13 support from `composer.json` and `ext_emconf.php`.
- Required TYPO3 14.3 packages explicitly: `core`, `backend`, `extbase`,
  `fluid`, and `frontend`.
- Updated extension version metadata to `2.0.0`.
- Replaced direct Guzzle service injection with TYPO3 Core `RequestFactory`.
- Added a TYPO3 14 CI workflow and a `Build/Scripts/runTests.sh` runner.

Verification:
- `composer validate --strict` passed.
- `composer update --dry-run --ignore-platform-req=php --no-interaction`
  resolved TYPO3 14.3.1 and `saschaegerer/phpstan-typo3` 3.0.1.

