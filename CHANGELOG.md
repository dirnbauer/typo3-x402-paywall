# Changelog

All notable changes to `webconsulting/typo3-x402-paywall` are documented in
this file.

## [1.2.0] - 2026-09-12

### Protocol

Re-verified on 2026-09-12 against the `coinbase/x402` repository:
[`specs/x402-specification-v2.md`](https://github.com/coinbase/x402/blob/main/specs/x402-specification-v2.md)
(document version v2.0, 2025-12-09),
[`specs/transports-v2/http.md`](https://github.com/coinbase/x402/blob/main/specs/transports-v2/http.md),
[`specs/schemes/exact/scheme_exact_evm.md`](https://github.com/coinbase/x402/blob/main/specs/schemes/exact/scheme_exact_evm.md)
and the live facilitator capabilities at `https://x402.org/facilitator/supported`.

- `PAYMENT-REQUIRED` now carries the complete `PaymentRequired` document
  (`x402Version: 2`, `resource: {url, description, mimeType}`, `accepts: [...]`)
  instead of a single bare requirement object.
- `PaymentRequirements` use the v2 field set: `amount` (atomic units) replaces
  `maxAmountRequired`, `asset` is the token contract address (was an object),
  `extra.name`/`extra.version` carry the EIP-712 domain of the token, and
  `resource`/`description`/`mimeType` moved into `ResourceInfo`.
- `PAYMENT-SIGNATURE` is decoded as a `PaymentPayload`
  (`x402Version`, `resource`, `accepted`, `payload`) and matched against the
  offered requirement before the facilitator is contacted.
- Facilitator API: `POST /verify` and `POST /settle` send
  `{x402Version, paymentPayload, paymentRequirements}` as JSON objects (no
  longer base64 strings) and read `isValid`/`invalidReason`/`payer` and
  `success`/`errorReason`/`transaction`/`network`/`payer`/`amount`.
  `GET /supported` replaces the root-URL health check and drives the
  simulator scenario and `PaymentVerifier::supportsRequirement()`.
- `PAYMENT-RESPONSE` is the base64 `SettlementResponse`
  (`success`, `transaction`, `network`, `payer`, `amount`); the previous
  `{scheme, network, txHash}` shape is gone.
- Settlement is attempted only after the TYPO3 response was produced with a
  2xx status; failed settlements answer 402 with the facilitator's
  `errorReason` and are logged with status `failed`.
- x402 v1 is **not** retired: the public facilitator still advertises
  `x402Version: 1` kinds. Compatibility therefore stays available behind the
  new `legacy_v1` site setting (default `false`): `X-PAYMENT` request header,
  v1 JSON body with `maxAmountRequired` and alias network names, and an
  `X-PAYMENT-RESPONSE` mirror. The non-standard `X-PAYMENT-REQUIRED` header
  fallback of the probe tool was removed; v1 bodies are still recognised.

### Added

- Standalone 402 paywall page for browsers (`Accept: text/html`) with an
  EIP-1193 wallet client that signs a real EIP-3009
  `TransferWithAuthorization` (domain from `extra.name`/`extra.version`,
  `chainId` from the CAIP-2 network, `verifyingContract` = `asset`) and
  retries the resource with `PAYMENT-SIGNATURE`.
- Site settings `asset_address`, `asset_decimals`, `asset_name`,
  `asset_version`, `max_timeout_seconds` and `legacy_v1`; `network` accepts
  CAIP-2 identifiers directly; `arbitrum` alias.
- Domain models `PaymentRequired`, `ResourceInfo`, `PaymentPayload`;
  `PaymentRequiredResponseFactory`; `PaymentVerifier::supported()`.
- `PaymentRequiredEvent::$network`, `PaymentReceivedEvent::$payer`; the
  payment log stores the payer reported by the facilitator.
- `x402_decode_header` explains `PaymentRequired`, `PaymentPayload` and
  `SettlementResponse` documents; `x402_transactions` returns payer and full
  transaction hash.
- Test suite: 79 unit tests (PHPUnit 13) and a functional test running the
  real frontend middleware stack (`typo3/testing-framework` 9, sqlite and
  MariaDB); `Build/phpunit/UnitTests.xml`, `Build/phpunit/FunctionalTests.xml`,
  `.php-cs-fixer.dist.php` (TYPO3 ruleset).
- Single GitHub Actions workflow: lint, cgl dry run, PHPStan, unit tests on
  PHP 8.4 (8.5 allowed failure), functional tests on sqlite and MariaDB 10.11.

### Changed

- Requires PHP 8.4+; `typo3/cms-extbase` is no longer a dependency.
- The middleware now runs after `typo3/cms-frontend/prepare-tsfe-rendering`
  so the page toggle is actually evaluated (it previously only saw the routing
  result, never the page record).
- PHPStan stays at level `max` (policy floor: 8) and now covers `tests/` and
  `Configuration/`.
- Build directory is `.Build/` (vendor, bin, public).
- README rewritten with a curl walk-through; manual refreshed for the v2 spec.

### Removed

- `nextjs-components/` (React/Next.js companion). It implemented the
  pre-1.2.0 header shapes and was never published; it has not moved anywhere.
  Use the official x402 JavaScript packages from `coinbase/x402` against the
  documented v2 flow instead.
- `tests/Debug/` probe scripts (superseded by the `x402_probe` MCP tool and
  the backend simulator) and the tracked `Build/Reports/` audit snapshots.
- Extbase plugins `X402Paywall/Paywall` and `X402Paywall/Verify`
  (`PaywallController`), their TypoScript, `tt_content` TCA, plugin icon and
  templates: the overlay could never render on a gated page because the
  middleware answered first, and the verify endpoint had no route.
- Site settings `pricing_mode` and `free_preview_paragraphs` (stored but
  unused), `PaywallConfigLike`, `ext_localconf.php`,
  `config/site-config.example.yaml` (kept in `Documentation/Configuration/`).

## [1.1.0] - 2026-08-06

### Changed

- Removed the conflicting legacy `mcp/sdk` runtime dependency.
- MCP tools now return SDK-neutral strings; the TYPO3 MCP server adapter
  converts them to the installed SDK's result types.

### Added

- Unit coverage for successful and exceptional SDK-neutral tool execution.

## [1.0.0] - 2026-05-24

### Added

- PSR-15 x402 paywall middleware for TYPO3 14.3+.
- Site-configuration based paywall settings.
- Page-level paywall fields for enablement, price, and payment prompt text.
- Frontend overlay plugin for wallet-driven payment verification.
- Backend dashboard for revenue statistics, top pages, and recent
  transactions.
- Backend x402 flow simulator for configured public URLs.
- Payment logging in `tx_x402_payment_log`.
- PSR-14 `PaymentRequiredEvent` and `PaymentReceivedEvent`.
- MCP tools for probing gated pages, decoding payment headers, reading
  payment stats, and listing recent transactions.
- React/Next.js companion source package under `nextjs-components/`.

### Security

- Server-side simulator and MCP probing reject non-public HTTP(S) targets.
