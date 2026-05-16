# TYPO3 conformance report after changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-conformance

Changes made:
- Composer and TER metadata now target TYPO3 14.3+ only.
- Added TYPO3 14.2+ Composer metadata under `extra.typo3/cms`.
- Added typed request-attribute access through `RequestAttributeResolver`.
- Reused `PaymentRequirement::fromConfig()` in middleware and controller.
- Removed deprecated/removed TYPO3 13 compatibility concerns from README.

Residual notes:
- `ext_emconf.php` is still kept for TER/classic compatibility even though
  TYPO3 14.2 deprecates it for TYPO3 15.
- Source code uses `src/` as the PSR-4 root and declares that in Composer.

