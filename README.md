# TYPO3 x402 Paywall

[![TYPO3 14.3+](https://img.shields.io/badge/TYPO3-14.3%2B-orange.svg)](https://get.typo3.org/14)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777bb4.svg)](https://www.php.net/)
[![x402 v2](https://img.shields.io/badge/x402-v2-1b7a95.svg)](https://github.com/x402-foundation/x402/blob/6fe0d4bfd104e8c61ae0b6aeaefe9da506d502ff/specs/x402-specification-v2.md)
[![PHPStan 8](https://img.shields.io/badge/PHPStan-level%208-blue.svg)](phpstan.neon)
[![CI](https://github.com/dirnbauer/typo3-x402-paywall/actions/workflows/ci.yml/badge.svg)](https://github.com/dirnbauer/typo3-x402-paywall/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Sell TYPO3 pages and API routes to humans and AI agents with the [x402](https://www.x402.org)
HTTP payment protocol: a gated resource answers `402 Payment Required`, the client pays in USDC
with a signed EIP-3009 authorization, a facilitator verifies and settles on-chain, and the content
is served in the same request. No accounts, no sessions, no card forms.

## What it is

- PSR-15 middleware implementing the x402 **v2** HTTP transport (`PAYMENT-REQUIRED`,
  `PAYMENT-SIGNATURE`, `PAYMENT-RESPONSE`), checked against the specification of the
  [x402 Foundation](https://github.com/x402-foundation/x402/tree/6fe0d4bfd104e8c61ae0b6aeaefe9da506d502ff/specs)
  and the live `x402.org` facilitator (`/verify`, `/settle`, `/supported`): failed settlements
  answer 402 with `PAYMENT-RESPONSE`, `settlement_pending` is retried once and logged as pending.
- Facilitators: `x402.org` (testnets only) or any compatible one, including the Coinbase
  Developer Platform facilitator with signed API-key tokens (`facilitator_auth: cdp`).
- Gating by page toggle (with price override and payment prompt), page UID list or route patterns.
- Wallet paywall page for browsers (EIP-1193 wallets such as MetaMask, Coinbase Wallet, Rabby).
- Payment log (`tx_x402_payment_log`) and the backend module **Content > x402 Paywall**:
  a dashboard with revenue per currency, settlement states and a configuration check per site,
  and a simulator that plays the client side against your sites.
- MCP tools `x402_probe`, `x402_decode_header`, `x402_gated_pages`, `x402_stats`,
  `x402_transactions` for agents (via `hn/typo3-mcp-server`).
- PSR-14 events `PaymentRequiredEvent` and `PaymentReceivedEvent`.
- Optional x402 v1 compatibility (`legacy_v1`) for clients that still send `X-PAYMENT`.

## Requirements

| Component   | Requirement                                                                              |
|-------------|------------------------------------------------------------------------------------------|
| TYPO3       | 14.3 LTS or later, Composer mode                                                         |
| PHP         | 8.4 or later                                                                             |
| Wallet      | EVM address on Base, Base Sepolia, Polygon, Arbitrum, Ethereum or any `eip155:<chainId>` |
| Facilitator | Outbound HTTPS to an x402 facilitator (default `https://x402.org/facilitator`, testnets)  |
| Optional    | `hn/typo3-mcp-server` to expose the MCP tools                                            |

## Install

```bash
composer require webconsulting/typo3-x402-paywall
vendor/bin/typo3 database:updateschema   # pages.tx_x402_* columns and tx_x402_payment_log
```

## Configure

Add an `x402_paywall` block to `config/sites/<site>/config.yaml`:

```yaml
x402_paywall:
  enabled: true
  wallet_address: "0xYOUR_WALLET"
  network: base-sepolia            # base | base-sepolia | polygon | arbitrum | ethereum | eip155:<chainId>
  facilitator_url: https://x402.org/facilitator
  default_price: "0.01"            # USDC, converted with asset_decimals (6)
  gated_route_patterns: ["/api/v1/content/*"]
  free_routes: ["/api/v1/health"]
  legacy_v1: false                 # also accept x402 v1 clients (X-PAYMENT)
  service_name: "Example Research" # optional discovery metadata: service_tags, service_icon_url
```

For mainnet, point `facilitator_url` to a mainnet facilitator, e.g. Coinbase CDP:

```yaml
x402_paywall:
  network: base
  facilitator_url: https://api.cdp.coinbase.com/platform/v2/x402
  facilitator_auth: cdp            # key from CDP_API_KEY_ID / CDP_API_KEY_SECRET or the settings below
  # facilitator_api_key_id: '%env(MY_CDP_KEY_ID)%'
  # facilitator_api_key_secret: '%env(MY_CDP_KEY_SECRET)%'
```

Then tick **Sell this page with x402** in the page properties of each paid page (tab *x402 Paywall*),
optionally with a price override and a prompt text. Route patterns gate headless endpoints
without a toggle; free routes always win. For tokens other than USDC set `asset_address`,
`asset_decimals`, `asset_name` and `asset_version` (the EIP-712 domain of the token).

## Use

```bash
# 1. Ask for the resource: 402 with the base64 PaymentRequired document in the header
curl -si -H 'Accept: application/json' https://example.com/premium | grep -i '^HTTP\|^PAYMENT-REQUIRED'
# 2. Decode it (the JSON body carries the same document)
curl -s -H 'Accept: application/json' https://example.com/premium | jq '.accepts[0]'
# { "scheme": "exact", "network": "eip155:84532", "amount": "10000", "asset": "0x036C…",
#   "payTo": "0xYOUR_WALLET", "maxTimeoutSeconds": 300, "extra": { "name": "USDC", "version": "2" } }
# 3. Pay: sign an EIP-3009 TransferWithAuthorization for accepts[0] with any x402 v2 client,
#    base64-encode the PaymentPayload and retry
curl -si -H "PAYMENT-SIGNATURE: $(cat payment-payload.b64)" https://example.com/premium | grep -i '^HTTP\|^PAYMENT-RESPONSE'
# HTTP/2 200  +  PAYMENT-RESPONSE: base64 {"success":true,"transaction":"0x…","network":"eip155:84532","payer":"0x…"}
# A payment that verifies but does not settle: HTTP 402 + PAYMENT-RESPONSE {"success":false,"errorReason":"…"}
```

Browsers (`Accept: text/html`) get a paywall page that connects a wallet, signs and reloads the
paid content. **Content > x402 Paywall** (admin) has a dashboard (revenue, settlement states,
latest attempts, configuration problems per site) and a simulator that requests a paywalled
page, sends a payment with an invalid signature or asks the facilitator what it supports.
Agents call the MCP tools, e.g. `x402_probe {"url": "https://example.com/premium"}`.

## Develop

```bash
composer install
composer ci                                          # lint, cgl, phpstan (level 8), unit, functional (sqlite)
Build/Scripts/runTests.sh -s functional -d mariadb   # needs typo3Database* environment variables
```

## Docs

Manual: [Documentation/Index.rst](Documentation/Index.rst) · Release notes:
[CHANGELOG.md](CHANGELOG.md) · Protocol:
[x402-foundation/x402 specs](https://github.com/x402-foundation/x402/tree/6fe0d4bfd104e8c61ae0b6aeaefe9da506d502ff/specs)

## License

GPL-2.0-or-later
