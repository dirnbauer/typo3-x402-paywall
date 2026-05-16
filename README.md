# TYPO3 x402 Paywall

**Charge AI agents and users for TYPO3 content access via the x402 payment protocol.**

Uses the HTTP 402 Payment Required standard to enable pay-per-request content monetization — no subscriptions, no accounts, no payment gateway integration needed.

[![TYPO3 v14](https://img.shields.io/badge/TYPO3-v14.3-orange.svg)](https://get.typo3.org/14)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-blue.svg)](phpstan.neon)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

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

## Features

- **PSR-15 middleware** — intercepts requests and enforces payment on gated routes/pages
- **Page-level gating** — enable paywall per page directly in the TYPO3 backend with custom price and description
- **Frontend plugin** — renders a paywall overlay with wallet payment UI for traditional TYPO3 sites
- **Backend dashboard** — revenue analytics with today/7d/30d/all-time stats, top pages, and recent transactions
- **Next.js components** — React hook, overlay component, and middleware for headless setups (`@webconsulting/typo3-x402-react`)
- **MCP tools** — gated-page discovery, x402 probing, header decoding, stats, and transaction inspection for agent workflows
- **PSR-14 events** — `PaymentRequiredEvent` and `PaymentReceivedEvent` for custom integrations
- **Payment log** — all transactions stored in `tx_x402_payment_log` for analytics
- **TYPO3 14 APIs** — outbound facilitator/probe calls use TYPO3 Core `RequestFactory`; frontend page data uses TYPO3 request attributes

## Installation

```bash
composer require webconsulting/typo3-x402-paywall
```

After installation, activate the extension and include the site set in your site configuration.

## Configuration

### Site Settings (TYPO3 v14.3)

Configure via the TYPO3 backend → Site Management → Site Configuration, or directly in your site's `config.yaml`:

| Setting | Description | Default |
|---------|-------------|---------|
| `x402_paywall.enabled` | Master on/off switch | `false` |
| `x402_paywall.wallet_address` | Your Ethereum/Base/Polygon wallet address | — |
| `x402_paywall.network` | Blockchain network: `base`, `base-sepolia`, `polygon`, `ethereum` | `base-sepolia` |
| `x402_paywall.facilitator_url` | x402 facilitator endpoint | `https://x402.org/facilitator` |
| `x402_paywall.currency` | Payment currency | `USDC` |
| `x402_paywall.default_price` | Default price in USDC | `0.01` |
| `x402_paywall.pricing_mode` | `per-request` or `per-page` | `per-request` |
| `x402_paywall.gated_route_patterns` | URI patterns to gate in headless/API mode | — |
| `x402_paywall.free_routes` | URI patterns always allowed through | — |
| `x402_paywall.gated_page_uids` | Page UIDs to gate as an alternative to the page toggle | — |

### Page-Level Pricing

In the TYPO3 backend, pages get three fields under the **x402 Paywall** tab:

- **Enable paywall** — toggle per page
- **Price** — override the default price (e.g. `0.05`)
- **Description** — shown in the payment overlay (e.g. `Premium article — access requires payment`)

### Headless / API Mode

For TYPO3 headless setups, the middleware intercepts requests on gated routes:

```
GET /api/v1/content/42
→ 402 Payment Required
→ Header: X-PAYMENT-REQUIRED: base64({price, currency, network, payTo, ...})

GET /api/v1/content/42 (with payment proof)
→ Header: X-PAYMENT-SIGNATURE: base64({signature, payload})
→ 200 OK + content
```

Configure gated routes in site settings:

```yaml
x402_paywall:
  enabled: true
  wallet_address: "0xYOUR_WALLET_ADDRESS"
  network: "base"
  gated_route_patterns:
    - "/api/v1/content/*"
    - "/api/v1/premium/*"
  free_routes:
    - "/api/v1/public/*"
```

## Frontend Plugin

Add the **x402 Paywall Overlay** plugin to any page as a content element. The plugin:

1. Detects if the current page is payment-gated
2. Renders a gradient fade-out over the page content
3. Shows a payment card with price, description, and network info
4. Handles wallet signing and payment verification via AJAX

## Backend Dashboard

The **x402 Paywall** backend module (under **Web**) shows:

- **Revenue cards** — total USDC earned today, last 7 days, last 30 days, and all-time
- **Top Pages** — top 10 monetized pages in the last 30 days (views + revenue)
- **Recent Transactions** — last 20 settled payments with wallet address, amount, and page

## Next.js / React Integration

Install the companion package:

```bash
npm install @webconsulting/typo3-x402-react
```

### Hook

```tsx
import { useX402Paywall } from '@webconsulting/typo3-x402-react';

function PremiumArticle() {
  const { state, content, fetchContent, confirmPayment } = useX402Paywall({
    apiUrl: '/api/v1/content/42',
    signPayment: async (requirement) => await wallet.sign(requirement),
  });

  if (state === 'paid') return <article>{content}</article>;
  if (state === 'payment_required') return (
    <button onClick={confirmPayment}>Pay {requirement.price} USDC</button>
  );
}
```

### All-in-one Component

```tsx
import { PaidContent } from '@webconsulting/typo3-x402-react';

<PaidContent
  apiUrl="/api/v1/content/42"
  signPayment={wallet.sign}
  preview={<p>First paragraph visible for free...</p>}
>
  {(content) => <article dangerouslySetInnerHTML={{ __html: content }} />}
</PaidContent>
```

### Next.js Middleware

```ts
// middleware.ts
import { withX402 } from '@webconsulting/typo3-x402-react/middleware';

export default withX402({
  typo3ApiUrl: process.env.TYPO3_API_URL,
  gatedPaths: ['/premium/**', '/api/v1/content/**'],
  freePaths: ['/api/v1/public/**'],
});
```

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  TYPO3 Request Pipeline                                      │
│                                                              │
│  PSR-15 Middleware Stack                                     │
│    └── X402PaywallMiddleware                                 │
│          1. RouteGateResolver — is this request gated?      │
│          2. Check X-PAYMENT-SIGNATURE header                 │
│          3. PaymentVerifier — verify via TYPO3 RequestFactory│
│          4. PaymentLogger — log transaction                  │
│          5. Pass through or return 402                       │
│                                                              │
│  Frontend Plugin (traditional TYPO3)                         │
│    └── PaywallController::show — render overlay             │
│    └── PaywallController::verify — AJAX payment check       │
│                                                              │
│  Backend Module                                              │
│    └── PaywallDashboardController — revenue analytics        │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
              ┌──────────────────────┐
              │  x402.org Facilitator │
              │  (Coinbase)           │
              │  verify + settle      │
              └──────────────────────┘
```

## PSR-14 Events

| Event | When | Use for |
|-------|------|---------|
| `PaymentRequiredEvent` | Before 402 response is sent | Custom pricing, logging, geo-blocking |
| `PaymentReceivedEvent` | After payment verified + settled | Unlock content, send receipts, webhooks |

## Requirements

- PHP 8.2+
- TYPO3 v14.3+
- A wallet address (Ethereum / Base / Polygon) to receive payments
- Internet access to x402 facilitator for payment verification

## Development

This extension targets TYPO3 14 only. TYPO3 13 support was dropped in v2.0.

```bash
composer install
composer validate --strict
Build/Scripts/runTests.sh -s ci
```

Quality gates:

- Composer validation and dependency audit
- PHP syntax linting
- PHPStan `level: max` with `saschaegerer/phpstan-typo3`
- PHPUnit unit tests

GitHub Actions runs the same checks on PHP 8.2, 8.3, 8.4, and 8.5.

## Roadmap

- ✅ **v1.0** — PSR-15 middleware, page-level gating, headless API support
- ✅ **v1.1** — Backend module with payment dashboard, revenue analytics
- ✅ **v1.2** — Next.js / React component library (`@webconsulting/typo3-x402-react`)
- ✅ **v2.0** — TYPO3 14.3-only support, TYPO3 Core HTTP API usage, PHPStan max, and MCP tools

## License

GPL-2.0-or-later

## Credits

- [x402 Protocol](https://www.x402.org/) by Coinbase
- Inspired by [Cloudflare EmDash](https://blog.cloudflare.com/emdash-wordpress/) built-in x402 support
- Built by [webconsulting.at](https://www.webconsulting.at)
