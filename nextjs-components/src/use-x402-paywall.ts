'use client';

import { useState, useCallback } from 'react';
import type { X402PaymentRequirement, X402PaymentResponse } from './x402-client';

export interface UseX402PaywallOptions {
  /** TYPO3 API endpoint URL */
  apiUrl: string;
  /** Called to sign the payment — integrate your wallet here */
  signPayment?: (requirement: X402PaymentRequirement) => Promise<string | null>;
}

export interface UseX402PaywallReturn {
  /** Current state */
  state: 'idle' | 'loading' | 'payment_required' | 'paying' | 'paid' | 'error';
  /** The fetched content (after payment if required) */
  content: unknown | null;
  /** Payment requirement details (when state is 'payment_required') */
  paymentInfo: {
    price: string;
    currency: string;
    description: string;
    network: string;
    requirement: X402PaymentRequirement | null;
  } | null;
  /** Transaction hash after successful payment */
  txHash: string | null;
  /** Error message if something went wrong */
  error: string | null;
  /** Fetch content — automatically handles 402 flow */
  fetchContent: (path: string) => Promise<void>;
  /** Confirm payment (when state is 'payment_required') */
  confirmPayment: () => Promise<void>;
  /** Reset to idle state */
  reset: () => void;
}

/**
 * React hook for x402 paywall integration with TYPO3.
 *
 * Usage:
 * ```tsx
 * const { state, content, paymentInfo, fetchContent, confirmPayment } = useX402Paywall({
 *   apiUrl: 'https://example.com/api/v1',
 *   signPayment: async (req) => { ... wallet signing ... },
 * });
 *
 * useEffect(() => { fetchContent('/content/42'); }, []);
 *
 * if (state === 'payment_required') {
 *   return <PaywallOverlay price={paymentInfo.price} onPay={confirmPayment} />;
 * }
 * ```
 */
export function useX402Paywall(options: UseX402PaywallOptions): UseX402PaywallReturn {
  const [state, setState] = useState<UseX402PaywallReturn['state']>('idle');
  const [content, setContent] = useState<unknown | null>(null);
  const [paymentInfo, setPaymentInfo] = useState<UseX402PaywallReturn['paymentInfo']>(null);
  const [txHash, setTxHash] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pendingPath, setPendingPath] = useState<string | null>(null);
  const [pendingRequirement, setPendingRequirement] = useState<X402PaymentRequirement | null>(null);

  const fetchContent = useCallback(async (path: string) => {
    setState('loading');
    setError(null);
    setContent(null);
    setPaymentInfo(null);
    setPendingPath(path);

    try {
      const url = `${options.apiUrl}${path}`;
      const response = await fetch(url);

      if (response.status === 402) {
        const data: X402PaymentResponse = await response.json();
        const req = data.x402?.requirements?.[0] ?? null;

        setState('payment_required');
        setPendingRequirement(req);
        setPaymentInfo({
          price: data.human_readable?.price ?? req?.maxAmountRequired ?? 'unknown',
          currency: req?.asset?.symbol ?? 'USDC',
          description: data.human_readable?.description ?? '',
          network: data.human_readable?.network ?? req?.network ?? '',
          requirement: req,
        });
        return;
      }

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const data = await response.json();
      setContent(data);
      setState('paid');
    } catch (err) {
      setState('error');
      setError(err instanceof Error ? err.message : 'Unknown error');
    }
  }, [options.apiUrl]);

  const confirmPayment = useCallback(async () => {
    if (!pendingRequirement || !pendingPath) {
      setError('No pending payment to confirm');
      return;
    }

    if (!options.signPayment) {
      setError('No signPayment handler configured');
      return;
    }

    setState('paying');

    try {
      const signature = await options.signPayment(pendingRequirement);

      if (!signature) {
        setState('payment_required');
        return;
      }

      const url = `${options.apiUrl}${pendingPath}`;
      const response = await fetch(url, {
        headers: { 'PAYMENT-SIGNATURE': signature },
      });

      if (response.status === 402) {
        setState('error');
        setError('Payment was rejected by the server');
        return;
      }

      // Extract tx hash from response header
      const paymentResponseHeader = response.headers.get('PAYMENT-RESPONSE');
      if (paymentResponseHeader) {
        try {
          const decoded = JSON.parse(atob(paymentResponseHeader));
          setTxHash(decoded.txHash ?? null);
        } catch {
          // ignore
        }
      }

      const data = await response.json();
      setContent(data);
      setState('paid');
      setPaymentInfo(null);
    } catch (err) {
      setState('error');
      setError(err instanceof Error ? err.message : 'Payment failed');
    }
  }, [pendingRequirement, pendingPath, options]);

  const reset = useCallback(() => {
    setState('idle');
    setContent(null);
    setPaymentInfo(null);
    setTxHash(null);
    setError(null);
    setPendingPath(null);
    setPendingRequirement(null);
  }, []);

  return { state, content, paymentInfo, txHash, error, fetchContent, confirmPayment, reset };
}
