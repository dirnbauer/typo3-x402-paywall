# TYPO3 conformance report before changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-conformance

Findings:
- Extension metadata is not TYPO3 14-only.
- `composer.json` does not declare all TYPO3 system extensions used by the code
  (`backend`, `extbase`, `fluid`).
- `composer.json` misses TYPO3 14.2+ Classic mode metadata
  `extra.typo3/cms.version` and `Package.providesPackages`.
- Frontend request attributes are used through untyped `mixed` values, which is
  weak for PHPStan max and TYPO3 14 API conformance.
- Some JSON encode/decode calls do not use `JSON_THROW_ON_ERROR`.

Suggested changes:
- Make Composer metadata explicit and synchronized with `ext_emconf.php`.
- Type-check TYPO3 request attributes such as `PageInformation` and
  `PageArguments`.
- Harden JSON handling and add domain helper methods to remove duplicated
  payment requirement logic.

