# TYPO3 testing report before changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-testing

Findings:
- Unit tests exist for configuration, route gating, and payment verification.
- `PaymentVerifierTest::testSettleReturnsFalseOnFailure()` documents behavior
  that the current implementation does not satisfy.
- There is no `Build/Scripts/runTests.sh` wrapper.
- There is no GitHub Actions workflow for TYPO3 14/PHP 8.2+.

Suggested changes:
- Fix the settlement behavior to satisfy the existing test.
- Add `Build/Scripts/runTests.sh` with `lint`, `phpstan`, `unit`, and `ci`
  suites.
- Wire the same suites into GitHub Actions.

