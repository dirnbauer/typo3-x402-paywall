# Security audit report after changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /security-audit

Changes made:
- Added GitHub Actions checks for Composer validation, dependency audit, PHP
  syntax linting, PHPStan max, and PHPUnit.
- Fixed settlement logic so HTTP 200 alone is not treated as settled; the
  facilitator body must explicitly contain `"settled": true`.
- Sanitized URL probing to allowed HTTP schemes.
- Removed direct application dependency on `guzzlehttp/guzzle`.

Verification:
- Composer metadata validation passed.
- Composer dependency resolution dry run passed for the TYPO3 14.3 dependency
  set.

