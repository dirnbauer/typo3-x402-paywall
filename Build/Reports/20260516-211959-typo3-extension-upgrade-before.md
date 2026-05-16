# TYPO3 extension upgrade report before changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-extension-upgrade

Findings:
- `composer.json` still allows TYPO3 13 via `^13.4 || ^14.0`.
- `ext_emconf.php` still allows TYPO3 13 via `13.4.0-14.99.99`.
- The extension uses direct Guzzle service injection for outbound HTTP calls instead
  of the TYPO3 `RequestFactory` API.
- PHPStan is configured at level 8, not `max`.
- No GitHub Actions workflow is present.

Suggested changes:
- Require TYPO3 14.3 only in Composer metadata.
- Mirror the TYPO3 14.3-only constraint in `ext_emconf.php`.
- Replace direct Guzzle injection with `TYPO3\CMS\Core\Http\RequestFactory`.
- Add the current TYPO3 PHPStan integration package and set `level: max`.
- Add a CI workflow that runs Composer validation, PHP linting, PHPStan, and
  PHPUnit on supported PHP versions for TYPO3 14.

