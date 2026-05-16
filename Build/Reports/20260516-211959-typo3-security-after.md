# TYPO3 security report after changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-security

Changes made:
- Replaced direct Guzzle injection with TYPO3 Core `RequestFactory`.
- Restricted simulator and MCP probe URLs to `http` and `https`.
- Switched IP log pseudonymization to TYPO3 Core `HashService` using
  `HashAlgo::SHA3_256`.
- Replaced silent JSON failures with exception-based JSON helpers.

Residual notes:
- The simulator and MCP probe remain intentionally capable of outbound HTTP
  probing. Redirects stay disabled and URL schemes are constrained.

