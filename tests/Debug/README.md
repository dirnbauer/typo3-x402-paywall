# x402 Paywall — Testing & Debugging

## Option 1: PHPUnit (no blockchain, instant)

Tests `PaymentVerifier` with a mocked Guzzle HTTP client.

```bash
composer install
./vendor/bin/phpunit tests/Unit/PaymentVerifierTest.php
./vendor/bin/phpunit tests/Unit/
```

---

## Option 2: PHP probe script (checks 402 response, no wallet needed)

Probes a running TYPO3 instance and shows the decoded payment requirement.

```bash
# Just check the 402 response
php tests/Debug/probe402.php https://your-typo3.local/gated-page

# Also send a mock signature (confirms middleware → facilitator path is wired)
php tests/Debug/probe402.php https://your-typo3.local/gated-page --with-mock-signature
```

---

## Option 3: Real end-to-end on Base Sepolia (no real money)

### 1. Set up a throwaway test wallet

```bash
node -e "
const {generatePrivateKey, privateKeyToAccount} = require('viem/accounts');
const key = generatePrivateKey();
console.log('Private key:', key);
console.log('Address:', privateKeyToAccount(key).address);
"
```

### 2. Fund with testnet tokens (free)

| Token | Faucet |
|-------|--------|
| ETH (gas on Base Sepolia) | https://docs.cdp.coinbase.com/faucets/introduction/welcome |
| USDC (Base Sepolia) | https://faucet.circle.com → select "Base Sepolia" |

### 3. Configure TYPO3 for Base Sepolia

```yaml
# config/sites/main/config.yaml
x402Paywall:
  enabled: true
  walletAddress: "0xYOUR_RECEIVING_WALLET"
  network: "base-sepolia"
  facilitatorUrl: "https://x402.org/facilitator"
  defaultPrice: "0.001"   # keep cheap for testing
```

### 4. Run the Node.js client

```bash
npm install @x402/client viem
PRIVATE_KEY=0xYOUR_TEST_KEY node tests/Debug/x402-client.mjs https://your-typo3.local/gated-page
```

The client automatically:
1. Makes a GET → gets 402
2. Signs a payment authorization with your test wallet
3. Retries with `PAYMENT-SIGNATURE` header
4. Logs the transaction hash → verify on https://sepolia.basescan.org

---

## Option 4: QuickNode x402 Explorer (GUI playground)

https://www.quicknode.com/sample-app-library/x402-explorer

A hosted playground to inspect x402 payment flows without writing code.

---

## Debugging the facilitator directly

```bash
# Check if the facilitator is reachable
curl https://x402.org/facilitator

# Manually test the verify endpoint
curl -s -X POST https://x402.org/facilitator/verify \
  -H "Content-Type: application/json" \
  -d '{"paymentPayload":"dGVzdA==","paymentRequirements":"dGVzdA=="}' | jq .
```

Expected response for invalid payload:
```json
{"valid": false, "error": "Invalid payment payload"}
```

---

## Content type debugging

To verify that `ContentTypeResolver` detects news/events correctly, add
`?tx_news_pi1[news]=42` to any gated URL and check the `content_type`
column in `tx_x402_payment_log` after a payment:

```sql
SELECT page_uid, content_type, content_uid, amount, status, crdate
FROM tx_x402_payment_log
ORDER BY crdate DESC
LIMIT 10;
```
