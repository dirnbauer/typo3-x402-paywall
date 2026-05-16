/**
 * x402 End-to-End Test Client (Node.js)
 *
 * Uses Coinbase's official @x402/client to make real payments on Base Sepolia.
 * No real money — uses testnet USDC.
 *
 * Setup:
 *   npm install @x402/client viem
 *
 * Fund your test wallet:
 *   1. ETH (gas): https://docs.cdp.coinbase.com/faucets/introduction/welcome
 *   2. USDC:      https://faucet.circle.com  (select "Base Sepolia")
 *
 * Usage:
 *   PRIVATE_KEY=0xYOUR_TEST_KEY node tests/Debug/x402-client.mjs https://your-typo3.local/gated-page
 *
 * WARNING: Never use a wallet with real funds. Generate a throwaway test key:
 *   node -e "const {generatePrivateKey} = require('viem/accounts'); console.log(generatePrivateKey())"
 */

import { wrapFetchWithPayment } from '@x402/client';
import { createWalletClient, http } from 'viem';
import { privateKeyToAccount } from 'viem/accounts';
import { baseSepolia } from 'viem/chains';

const url = process.argv[2];
const privateKey = process.env.PRIVATE_KEY;

if (!url || !privateKey) {
  console.error('Usage: PRIVATE_KEY=0x... node x402-client.mjs <url>');
  process.exit(1);
}

const account = privateKeyToAccount(privateKey);
console.log(`\nWallet: ${account.address}`);
console.log(`URL:    ${url}\n`);

// Create a viem wallet client on Base Sepolia
const walletClient = createWalletClient({
  account,
  chain: baseSepolia,
  transport: http(),
});

// Wrap fetch with x402 payment handling
const payingFetch = wrapFetchWithPayment(fetch, walletClient);

console.log('→ Making x402 payment request...');

try {
  const response = await payingFetch(url, {
    headers: { Accept: 'application/json' },
  });

  console.log(`  HTTP status: ${response.status}`);

  if (response.status === 200) {
    const body = await response.json();
    console.log('  ✓ Payment accepted! Content received:');
    console.log(JSON.stringify(body, null, 2).slice(0, 500));

    const txHash = response.headers.get('PAYMENT-RESPONSE');
    if (txHash) {
      const decoded = JSON.parse(Buffer.from(txHash, 'base64').toString());
      console.log(`\n  Transaction hash: ${decoded.txHash}`);
      console.log(`  View on explorer: https://sepolia.basescan.org/tx/${decoded.txHash}`);
    }
  } else {
    const body = await response.text();
    console.log('  ✗ Unexpected response:', body.slice(0, 300));
  }
} catch (err) {
  console.error('  Error:', err.message);
}
