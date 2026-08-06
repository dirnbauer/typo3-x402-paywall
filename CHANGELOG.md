# Changelog

All notable changes to `webconsulting/typo3-x402-paywall` are documented in
this file.

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
