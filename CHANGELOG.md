# Changelog

All notable changes to `webconsulting/typo3-x402-paywall` are documented in
this file.

## [1.4.1] - 2026-09-27

### Fixed

- The simulator runs its scenarios again. It answered every run with "The simulator request is
  invalid." because `AjaxRequest` posts form data unless the request says it is JSON, and the
  run action reads a JSON body. The request now carries `Content-Type: application/json`.

## [1.4.0] - 2026-09-23

### Protocol

Checked on 2026-09-23 against the specification of the x402 Foundation
([`x402-foundation/x402`](https://github.com/x402-foundation/x402) at
`6fe0d4bfd104e8c61ae0b6aeaefe9da506d502ff`; the former `coinbase/x402` repository is no
longer maintained): `specs/x402-specification-v2.md`, `specs/transports-v2/http.md`,
`specs/schemes/exact/scheme_exact_evm.md`, the TypeScript reference server and
`GET https://x402.org/facilitator/supported`. The protocol is still x402 v2; header names
are unchanged.

- A payment that verifies but does not settle answers `402` with the `SettlementResponse`
  in `PAYMENT-RESPONSE` (`success: false`, `errorReason`, `transaction`, `network`, `payer`)
  and a JSON copy as body, no longer with `PAYMENT-REQUIRED` (HTTP transport v2,
  "Settlement Response Delivery"). x402 v1 clients keep the v1 body plus `X-PAYMENT-RESPONSE`.
- `settlement_pending` (broadcast, not yet confirmed; always with a transaction hash) is
  retried once with the identical request, as the reference servers do. If it stays pending,
  the 402 carries the hash and the payment log records the new status `pending`. A settle
  request that got no answer after it was sent is logged as `pending` too
  (`unexpected_settle_error` towards the client), a failure while connecting as `failed`.
- The payment payload is forwarded to the facilitator byte for byte: JSON objects stay
  objects, so an empty `"extensions": {}` no longer turns into `[]`.
- `accepted` must repeat the offered requirement exactly: `maxTimeoutSeconds` and every
  `extra` entry the server declared are compared too; the reserved `extra` keys
  `assetTransferMethod` and `paymentFlow` are accepted only with the implemented values
  `eip3009` and `authorization`.
- `ResourceInfo.mimeType` describes the resource (`text/html` for pages, omitted for other
  page types) instead of echoing the client's `Accept` header; new optional `serviceName`,
  `tags` and `iconUrl` from the site settings `service_name`, `service_tags`,
  `service_icon_url` (validated against the limits of the specification).
- Facilitator answers: `errorMessage` / `invalidMessage` are kept apart from the reason code,
  `SettlementResponse.extensions` is passed through, answers with a result body count
  whatever their HTTP status (CDP reports `settlement_pending` with 500), and the
  specification codes `unexpected_verify_error` / `unexpected_settle_error` replace
  `facilitator_unreachable`, `verification_failed`, `settlement_failed` and
  `invalid_facilitator_response`.

### Added

- Coinbase Developer Platform facilitator: `facilitator_auth: cdp` adds
  `Authorization: Bearer <JWT>` to every facilitator request, a two-minute token bound to
  method and URL, signed with an ECDSA (ES256) or Ed25519 (EdDSA) CDP API key from
  `facilitator_api_key_id` / `facilitator_api_key_secret` or the environment variables
  `CDP_API_KEY_ID` / `CDP_API_KEY_SECRET` (`FacilitatorAuthentication`).
- `PaymentVerifier::supported()` (`GET /supported`), `GatedPageFinder`,
  `PaymentLogger::getRevenueByCurrency()` / `countByStatus()` / `status()`,
  `PaywallConfiguration::getProblems()` and block explorer links for known networks.

### Changed

- Backend module rebuilt with native TYPO3 v14 components: **Content > x402 Paywall**
  (path unchanged, `/module/web/x402-paywall`) is a module group with the submodules
  **Dashboard** and **Simulator**, switched in the document header; no page tree.
  - Dashboard: settled revenue per period and currency (amounts are no longer summed across
    currencies or labelled USDC), a warning for pending settlements, every site with an
    `x402_paywall` block and the mistakes that keep payments from working (for example a
    mainnet network on the testnet-only x402.org facilitator, or CDP authentication without
    a key), the latest attempts with status badge, payer and block explorer link, top pages.
  - Simulator: scenarios with real targets of the selected site (its first paywalled page,
    its first gated route), a payment with an invalid signature, and a facilitator
    capabilities check with the site's credentials; every request and response with the
    decoded `PAYMENT-REQUIRED`, `PAYMENT-SIGNATURE` and `PAYMENT-RESPONSE` headers. The
    site's own host is allowed even when it resolves to a private address (local
    development); other targets must be public. ES module with `AjaxRequest` and
    `~labels`, rendered as text only.
  - Route identifiers: `web_x402_paywall_dashboard`, `web_x402_paywall_simulator`,
    `web_x402_paywall_simulator.run` replace `web_x402_paywall.simulator` and
    `web_x402_paywall.runSimulation`. The module is offered in the live workspace only.
- Page properties: clearer labels with descriptions, the fields appear as soon as the
  toggle is switched on; the log status is a read-only select (settled, pending, failed).
- Wallet paywall page: reads `PAYMENT-RESPONSE` and tells the payer to check the wallet
  before paying again when a settlement is pending or in an unknown state.
- Module labels live in `Resources/Private/Language/Modules/*.xlf` and
  `locallang_mod.xlf` (translation domains `x402_paywall.modules.*`, `x402_paywall.mod`);
  all XLIFF files in English and German with two-space indentation. Line-art module icon.
- PHP 8.4 idioms: typed class constants, `array_find` / `array_any` / `array_all`,
  first-class callables, readonly services; PHPStan level 8 with deprecation rules.
- Dependencies: `firebase/php-jwt` ^7.1, `guzzlehttp/guzzle` ^7.15.2 || ^8.0,
  `psr/http-client`, `psr/http-factory` declared; PHPStan ^2.2, PHPUnit ^13.3,
  testing-framework ^9.7. CI: PHP 8.4 and 8.5 as required jobs, MariaDB 11.4,
  `actions/checkout` v7.
- Capabilities: `api.cdp.coinbase.com` and configurable facilitator hosts declared.

### Removed

- `PaywallDashboardController::simulatorAction()` / `runSimulationAction()` (now
  `PaywallSimulatorController`), the template `Dashboard/Simulator.html` and the classic
  script `Resources/Public/JavaScript/simulator.js`.
- The obsolete TCA `ctrl` entries `delete => ''`, `readOnly`, `hideTable` and the composer
  `app-dir` setting.

### Upgrade

No database update: `pending` is a new value of the existing `status` column. New site
settings are optional. Clients that parsed `PAYMENT-REQUIRED` of a failed settlement read
`PAYMENT-RESPONSE` instead.

## [1.3.0] - 2026-09-18

### Protocol

Re-verified on 2026-09-18 against `coinbase/x402`
[`specs/x402-specification-v2.md`](https://github.com/coinbase/x402/blob/main/specs/x402-specification-v2.md)
(still document version v2.0, 2025-12-09),
[`specs/transports-v2/http.md`](https://github.com/coinbase/x402/blob/main/specs/transports-v2/http.md)
and `GET https://x402.org/facilitator/supported`. Header names, the
`PaymentRequired` / `PaymentPayload` / `SettlementResponse` shapes and the
`/verify` and `/settle` request bodies are unchanged; the transport spec
declares 402 bodies an implementation concern (the JSON copy stays). x402 v1
is not retired (the facilitator still advertises `x402Version: 1` kinds), so
`legacy_v1` stays available.

- `PaymentRequired.extensions` (optional in the spec) is decoded and
  re-emitted.
- The client's `PaymentPayload` is forwarded to the facilitator verbatim
  instead of being re-serialised from parsed fields, so `extensions` and
  scheme-specific fields such as `assetTransferMethod` survive.
- `PAYMENT-RESPONSE` on a v1 payload sent through `PAYMENT-SIGNATURE` is now
  mirrored into `X-PAYMENT-RESPONSE` as well.

### Changed

- Typed facilitator results: `PaymentVerifier::verify()` returns a
  `VerifyResponse`, `settle()` a `SettlementResponse` (also the model behind
  the `PAYMENT-RESPONSE` header and the payment log).
- Everything x402 v1 (headers, alias networks, `maxAmountRequired` body,
  top-level scheme/network) lives in `Legacy\X402V1`; the domain models are
  pure v2.
- `ReportingPeriod` enum replaces the duplicated period `match` in the
  dashboard controller and `x402_stats`.
- `RouteGateResolver` absorbs `RequestAttributeResolver`;
  `PaymentLogger::logPayment()` takes the `SettlementResponse`.
- The simulator builds the mock `PAYMENT-SIGNATURE` from the requirement the
  probed URL actually offers, so the facilitator rejection is real; the
  response is `{status, headers, body, decodedRequirement}`.
- Backend labels are resolved through `LanguageServiceFactory`.
- MCP tools are tagged once via `_instanceof` in `Services.yaml`.
- PHPStan level 8 (portfolio policy, was `max`); dependencies refreshed
  (PHPUnit 13.3, testing-framework 9.7, PHPStan 2.2).
- README and manual rewritten; the Security chapter merged into Developer, a
  Changelog chapter added.

### Removed

- `RequestAttributeResolver`, the `statsAction` AJAX route
  (`Configuration/Backend/AjaxRoutes.php`), `PaymentVerifier::supported()`,
  `supportsRequirement()` and `testConnection()`,
  `PaywallConfiguration::getChainId()`, `PaymentRequired::toLegacyArray()`,
  `PaymentRequirement::toLegacyArray()`, `PaymentPayload::isLegacy()`, the
  pre-1.2.0 object-asset shim, the unused `chainId` / base64 data attributes
  of the paywall page and the fabricated `steps` of the simulator response.

### Added

- Tests for `X402V1`, `SettlementResponse`, `VerifyResponse`,
  `ReportingPeriod`, `ConfigurationProvider`, `ScalarValue` / `Json`, route
  pattern gating through the real middleware stack, and a functional test of
  the payment log queries and the log-backed MCP tools (99 unit, 8 functional).

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
