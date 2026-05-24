# TYPO3 x402 Paywall

Charge AI agents and users for TYPO3 content access with the x402 HTTP
payment protocol.

[![TYPO3 v14.3+](https://img.shields.io/badge/TYPO3-v14.3%2B-orange.svg)](https://get.typo3.org/14)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-blue.svg)](phpstan.neon)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

The extension returns `402 Payment Required` for configured pages and routes,
verifies `PAYMENT-SIGNATURE` headers with an x402 facilitator, logs settled
payments, and exposes TYPO3 backend analytics for monetized content.

## Requirements

- TYPO3 14.3 or later
- PHP 8.2 or later
- A wallet address on Base, Base Sepolia, Polygon, or Ethereum
- Outbound HTTPS access to the configured x402 facilitator

## Installation

```bash
composer require webconsulting/typo3-x402-paywall
```

Configure `x402_paywall` in your TYPO3 site configuration and add the
**x402 paywall overlay** plugin where traditional frontend pages need the
wallet prompt.

```yaml
x402_paywall:
  enabled: true
  wallet_address: "0xYOUR_WALLET_ADDRESS"
  network: "base-sepolia"
  default_price: "0.01"
  gated_route_patterns:
    - "/api/v1/content/*"
```

See [Documentation/Installation/Index.rst](Documentation/Installation/Index.rst)
and [Documentation/Configuration/Index.rst](Documentation/Configuration/Index.rst)
for the full setup reference.

## Features

- PSR-15 middleware for x402 payment enforcement
- Per-page paywall toggle, price override, and payment prompt description
- Headless route gating for API and AI-agent traffic
- Frontend overlay plugin with EIP-1193 wallet signing flow
- Backend dashboard with revenue, top pages, and recent transactions
- PSR-14 events for payment-required and payment-received integrations
- MCP tools for gated-page discovery, probing, stats, transaction lookup, and
  payment header decoding
- Optional React/Next.js companion package in `nextjs-components/`

## Development

```bash
composer install
composer validate --strict
Build/Scripts/runTests.sh -s ci
```

GitHub Actions runs the same quality gates on PHP 8.2, 8.3, 8.4, and 8.5.

## Documentation

The TYPO3 manual starts at
[Documentation/Index.rst](Documentation/Index.rst). Release notes are in
[CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later
