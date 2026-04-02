/**
 * x402 client for communicating with a TYPO3 x402-paywall backend.
 * Handles the 402 → pay → retry flow.
 */

export interface X402PaymentRequirement {
  scheme: string;
  network: string;
  maxAmountRequired: string;
  resource: string;
  description: string;
  maxTimeoutSeconds: number;
  payTo: string;
  asset: {
    address: string;
    symbol: string;
    decimals: number;
  };
}

export interface X402PaymentResponse {
  status: number;
  message: string;
  x402: {
    version: string;
    requirements: X402PaymentRequirement[];
  };
  human_readable: {
    price: string;
    description: string;
    network: string;
  };
}

export interface X402ClientConfig {
  /** Base URL of the TYPO3 API (e.g., https://example.com/api/v1) */
  baseUrl: string;
  /** Called when a 402 response is received — implement your wallet signing here */
  onPaymentRequired: (requirement: X402PaymentRequirement) => Promise<string | null>;
  /** Optional: custom fetch implementation */
  fetch?: typeof fetch;
}

/**
 * Create an x402-aware fetch client.
 *
 * Usage:
 * ```ts
 * const client = createX402Client({
 *   baseUrl: 'https://example.com/api/v1',
 *   onPaymentRequired: async (req) => {
 *     // Show wallet dialog, sign payment
 *     return signedPaymentBase64;
 *   },
 * });
 *
 * const article = await client.fetch('/content/42');
 * ```
 */
export function createX402Client(config: X402ClientConfig) {
  const fetchFn = config.fetch ?? globalThis.fetch;

  async function x402Fetch<T = unknown>(
    path: string,
    options?: RequestInit
  ): Promise<{ data: T; paid: boolean; txHash?: string }> {
    const url = `${config.baseUrl}${path}`;

    // First request — may return 402
    const response = await fetchFn(url, options);

    if (response.status !== 402) {
      // Not gated or already paid
      const data = await response.json();
      return { data, paid: false };
    }

    // Parse 402 response
    const paymentResponse: X402PaymentResponse = await response.json();
    const requirement = paymentResponse.x402?.requirements?.[0];

    if (!requirement) {
      throw new X402Error('Invalid 402 response: no payment requirements', paymentResponse);
    }

    // Call the payment handler
    const paymentSignature = await config.onPaymentRequired(requirement);

    if (!paymentSignature) {
      throw new X402Error('Payment cancelled by user', paymentResponse);
    }

    // Retry with payment proof
    const paidResponse = await fetchFn(url, {
      ...options,
      headers: {
        ...options?.headers,
        'PAYMENT-SIGNATURE': paymentSignature,
      },
    });

    if (paidResponse.status === 402) {
      throw new X402Error('Payment rejected by server', await paidResponse.json());
    }

    const paymentResponseHeader = paidResponse.headers.get('PAYMENT-RESPONSE');
    let txHash: string | undefined;
    if (paymentResponseHeader) {
      try {
        const decoded = JSON.parse(atob(paymentResponseHeader));
        txHash = decoded.txHash;
      } catch {
        // ignore
      }
    }

    const data = await paidResponse.json();
    return { data, paid: true, txHash };
  }

  return { fetch: x402Fetch };
}

export class X402Error extends Error {
  constructor(
    message: string,
    public readonly response: X402PaymentResponse
  ) {
    super(message);
    this.name = 'X402Error';
  }

  get price(): string {
    return this.response.human_readable?.price ?? 'unknown';
  }

  get description(): string {
    return this.response.human_readable?.description ?? '';
  }
}
