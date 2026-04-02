# TYPO3 x402 Paywall

**Charge AI agents and users for TYPO3 content access via the x402 payment protocol.**

Uses the HTTP 402 Payment Required standard to enable pay-per-request content monetization — no subscriptions, no accounts, no payment gateway integration needed.

## What is x402?

[x402](https://www.x402.org/) is an open payment protocol built on the long-dormant HTTP 402 status code. A client requests a resource, the server responds with `402 Payment Required` and payment terms, the client pays (via stablecoins like USDC), and the server delivers the content.

```
Client ──GET /api/article/42──▶ TYPO3
                                  │
TYPO3 ──402 + payment terms──────▶ Client
                                  │
Client ──GET + payment proof─────▶ TYPO3
                                  │
TYPO3 ──verify via facilitator───▶ Coinbase Facilitator
                                  │
TYPO3 ──200 + content────────────▶ Client
```

## Why?

- **AI agents** are the new content consumers. They don't see ads, don't click affiliate links, and don't subscribe. x402 lets them pay per request.
- **Micropayments** at scale: charge $0.001–$10 per API call or page view.
- **Zero friction**: no accounts, no API keys, no checkout pages.
- **TYPO3 headless APIs** become revenue streams automatically.

## Installation

```bash
composer require webconsulting/typo3-x402-paywall
```

## Configuration

### Site Set (TYPO3 v13+/v14)

Add the extension's site set to your site configuration, then configure via `config/system/settings.yaml` or the TYPO3 backend:

```yaml
# config/system/settings.yaml
x402_paywall:
  enabled: true
  wallet_address: "0xYOUR_WALLET_ADDRESS"
  network: "base-sepolia"
  facilitator_url: "https://x402.org/facilitator"
  currency: "USDC"
  default_price: "0.01"
  pricing_mode: "per-request"
```

### Page-Level Pricing

In the TYPO3 backend, each page/content element gets an "x402 Paywall" tab:

- **Enable paywall**: Toggle per page
- **Price**: Override default price for this page
- **Free preview**: Number of paragraphs shown before paywall kicks in

### Headless API Mode

For TYPO3 headless setups (e.g., with your Next.js frontend), the middleware intercepts API requests:

```
GET /api/v1/content/42
→ 402 Payment Required
→ Header: PAYMENT-REQUIRED: base64({price: "0.01", currency: "USDC", ...})

GET /api/v1/content/42
→ Header: PAYMENT-SIGNATURE: base64({signature, payload})
→ 200 OK + full content JSON
```

## Architecture

```
┌─────────────────────────────────────────────────────┐
│  TYPO3 Request Pipeline                              │
│                                                      │
│  ┌──────────────┐   ┌──────────────────────────┐    │
│  │ PSR-15       │──▶│ X402PaywallMiddleware     │    │
│  │ Middleware   │   │                          │    │
│  │ Stack       │   │ 1. Check if route gated  │    │
│  └──────────────┘   │ 2. Check payment header  │    │
│                      │ 3. Verify via facilitator│    │
│                      │ 4. Pass through or 402   │    │
│                      └──────────────────────────┘    │
│                              │                       │
│                              ▼                       │
│                      ┌──────────────────┐            │
│                      │ PaymentVerifier  │            │
│                      │ (Facilitator API)│            │
│                      └──────────────────┘            │
└─────────────────────────────────────────────────────┘
```

## CLI Commands

```bash
# Show payment statistics
typo3 x402:stats

# Test facilitator connectivity
typo3 x402:test-connection

# List all gated pages/routes
typo3 x402:list-gated
```

## Requirements

- PHP 8.2+
- TYPO3 v13.4 or v14.0+
- A wallet address (Ethereum/Base/Polygon) to receive payments
- Internet access to x402 facilitator for payment verification

## Roadmap

- **v1.0**: PSR-15 middleware, page-level gating, headless API support
- **v1.1**: Backend module with payment dashboard, revenue analytics
- **v1.2**: Next.js frontend component library (wallet connect UI)
- **v2.0**: MCP server integration — expose TYPO3 content as paid MCP tools

## License

GPL-2.0-or-later

## Credits

- [x402 Protocol](https://www.x402.org/) by Coinbase
- Inspired by [Cloudflare EmDash](https://blog.cloudflare.com/emdash-wordpress/) built-in x402 support
- Built by [webconsulting.at](https://www.webconsulting.at)
