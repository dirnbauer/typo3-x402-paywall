# TYPO3 x402 Paywall

[![TYPO3 14.3+](https://img.shields.io/badge/TYPO3-14.3%2B-orange.svg)](https://get.typo3.org/14)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777bb4.svg)](https://www.php.net/)
[![x402 v2](https://img.shields.io/badge/x402-v2-1b7a95.svg)](https://github.com/coinbase/x402/blob/main/specs/x402-specification-v2.md)
[![PHPStan max](https://img.shields.io/badge/PHPStan-max-blue.svg)](phpstan.neon)
[![CI](https://github.com/dirnbauer/typo3-x402-paywall/actions/workflows/ci.yml/badge.svg)](https://github.com/dirnbauer/typo3-x402-paywall/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## What it is

A PSR-15 middleware that sells TYPO3 pages and API routes to humans and AI agents with the
[x402](https://www.x402.org) HTTP payment protocol (specification v2.0, 2025-12-09). Gated
resources answer `402 Payment Required` with a `PAYMENT-REQUIRED` header, clients pay in USDC
(or any ERC-20 with EIP-3009), the configured facilitator verifies and settles, and the
content is served with a `PAYMENT-RESPONSE` header. Browsers get a wallet paywall page,
editors get a revenue dashboard, agents get five MCP tools.

## Requirements

- TYPO3 14.3+, PHP 8.4+ (8.5 supported), Composer mode.
- A receiving wallet on Base, Base Sepolia, Polygon, Arbitrum or any `eip155:*` network.
- Outbound HTTPS to an x402 facilitator (`https://x402.org/facilitator` by default).

## Install

```bash
composer require webconsulting/typo3-x402-paywall
vendor/bin/typo3 database:updateschema   # adds pages.tx_x402_* and tx_x402_payment_log
```

## Configure

Add an `x402_paywall` block to `config/sites/<site>/config.yaml`:

```yaml
x402_paywall:
  enabled: true
  wallet_address: "0xYOUR_WALLET"
  network: base-sepolia          # base | base-sepolia | polygon | arbitrum | eip155:<chainId>
  facilitator_url: https://x402.org/facilitator
  default_price: "0.01"          # in USDC (asset_decimals: 6)
  gated_route_patterns: ["/api/v1/content/*"]
  free_routes: ["/api/v1/health"]
  gated_page_uids: []
  legacy_v1: false               # also accept x402 v1 clients (X-PAYMENT)
```

- **Pages**: tick *Enable x402 paywall* in the page properties; optional price override and
  prompt text. News/blog/event detail records are logged by their own type and UID.
- **Routes**: `gated_route_patterns` and `free_routes` accept exact paths, `/prefix/*` and
  glob patterns; free routes win.
- **Facilitator**: `facilitator_url` is called on `/verify`, `/settle` and `/supported`.
- **Networks/assets**: aliases map to the native USDC deployment. For other tokens or
  networks set `asset_address`, `asset_decimals`, `asset_name`, `asset_version`.
- **MCP tools** (tag `mcp.tool`, served by `hn/typo3-mcp-server`):
  `x402_probe` (GET a public URL, decode its 402), `x402_decode_header` (explain a
  PAYMENT-REQUIRED / PAYMENT-SIGNATURE / PAYMENT-RESPONSE value), `x402_gated_pages`,
  `x402_stats` (today/7days/30days/all), `x402_transactions` (recent log entries).

## Use

```bash
# 1. Ask for the resource: 402 with the base64 PaymentRequired document in the header
curl -si -H 'Accept: application/json' https://example.com/premium | grep -i '^HTTP\|^PAYMENT-REQUIRED'
# HTTP/2 402
# PAYMENT-REQUIRED: eyJ4NDAyVmVyc2lvbiI6MiwicmVzb3VyY2UiOnsidXJsIjoiaHR0cHM6Ly9leGFtcGxlLmNvbS9wcmVtaXVtIi...

# 2. Decode it (the JSON body is the same document)
curl -s -H 'Accept: application/json' https://example.com/premium | jq '.accepts[0]'
# { "scheme": "exact", "network": "eip155:84532", "amount": "10000",
#   "asset": "0x036CbD53842c5426634e7929541eC2318f3dCF7e", "payTo": "0xYOUR_WALLET",
#   "maxTimeoutSeconds": 300, "extra": { "name": "USDC", "version": "2" } }

# 3. Pay: sign an EIP-3009 TransferWithAuthorization for accepts[0] with any x402 v2 client
#    (e.g. the @x402 packages from github.com/coinbase/x402, or a browser on the paywall page),
#    base64-encode the PaymentPayload and retry
curl -si -H "PAYMENT-SIGNATURE: $(cat payment-payload.b64)" https://example.com/premium | grep -i '^HTTP\|^PAYMENT-RESPONSE'
# HTTP/2 200
# PAYMENT-RESPONSE: eyJzdWNjZXNzIjp0cnVlLCJ0cmFuc2FjdGlvbiI6IjB4...   -> {"success":true,"transaction":"0x…","network":"eip155:84532","payer":"0x…"}
```

Browsers (`Accept: text/html`) receive a paywall page that connects an EIP-1193 wallet,
signs the authorization and reloads the paid content. The backend module
*Web > x402 Paywall* shows revenue, top pages, recent transactions and a request simulator.

## Develop

```bash
composer install
composer ci                                  # lint, cgl, phpstan (max), unit, functional (sqlite)
Build/Scripts/runTests.sh -s functional -d mariadb   # needs typo3Database* env vars
```

## Docs

Manual: [Documentation/Index.rst](Documentation/Index.rst) · Release notes:
[CHANGELOG.md](CHANGELOG.md) · Protocol: [coinbase/x402 specs](https://github.com/coinbase/x402/tree/main/specs)

## License

GPL-2.0-or-later
