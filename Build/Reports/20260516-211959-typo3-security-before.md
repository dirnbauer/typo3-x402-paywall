# TYPO3 security report before changes

Date: 2026-05-16 21:19:59 Europe/Vienna

Skill: /typo3-security

Findings:
- Outbound HTTP is implemented through direct Guzzle injection rather than TYPO3
  Core's HTTP API.
- Simulator and MCP tools accept arbitrary probe URLs. This is intentional
  functionality, but it should validate schemes and keep redirects disabled to
  reduce SSRF risk.
- Payment log stores hashed IP values without an installation-specific salt.
- JSON handling can silently fail and generate falsey values.

Suggested changes:
- Use TYPO3 `RequestFactory` for all external HTTP calls.
- Validate probe/simulation URLs to `http` and `https` only.
- Use TYPO3 encryption key as an HMAC key for IP hashing.
- Use exception-based JSON helpers where payloads must be valid.

