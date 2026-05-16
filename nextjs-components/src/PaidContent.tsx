'use client';

import React, { useEffect } from 'react';
import { useX402Paywall } from './use-x402-paywall';
import { PaywallOverlay } from './PaywallOverlay';
import type { X402PaymentRequirement } from './x402-client';

export interface PaidContentProps {
  /** TYPO3 headless API base URL */
  apiUrl: string;
  /** Content path (e.g., '/content/42') */
  contentPath: string;
  /** Wallet signing function */
  signPayment: (requirement: X402PaymentRequirement) => Promise<string | null>;
  /** Render function for the paid content */
  children: (content: unknown) => React.ReactNode;
  /** Preview content shown above the paywall */
  preview?: React.ReactNode;
  /** Loading component */
  loading?: React.ReactNode;
  /** Custom CSS class */
  className?: string;
}

/**
 * All-in-one component for x402-gated TYPO3 content.
 *
 * Handles the complete flow: fetch → 402 → show paywall → pay → show content.
 *
 * Usage:
 * ```tsx
 * <PaidContent
 *   apiUrl="https://example.com/api/v1"
 *   contentPath="/content/42"
 *   signPayment={async (req) => walletSign(req)}
 *   preview={<p>Preview of the article...</p>}
 * >
 *   {(content) => <Article data={content} />}
 * </PaidContent>
 * ```
 */
export function PaidContent({
  apiUrl,
  contentPath,
  signPayment,
  children,
  preview,
  loading,
  className,
}: PaidContentProps) {
  const {
    state,
    content,
    paymentInfo,
    error,
    fetchContent,
    confirmPayment,
    reset,
  } = useX402Paywall({ apiUrl, signPayment });

  useEffect(() => {
    fetchContent(contentPath);
  }, [contentPath, fetchContent]);

  if (state === 'idle' || state === 'loading') {
    return loading ?? (
      <div className={className} style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
        Loading...
      </div>
    );
  }

  if (state === 'payment_required' || state === 'paying') {
    return (
      <PaywallOverlay
        price={paymentInfo?.price ?? ''}
        description={paymentInfo?.description ?? ''}
        network={paymentInfo?.network ?? ''}
        state={state}
        error={error}
        onPay={confirmPayment}
        onCancel={reset}
        className={className}
      >
        {preview}
      </PaywallOverlay>
    );
  }

  if (state === 'error') {
    return (
      <div className={className} style={{ padding: '20px', textAlign: 'center' }}>
        <p style={{ color: '#dc2626' }}>{error}</p>
        <button onClick={() => fetchContent(contentPath)} style={{ cursor: 'pointer' }}>
          Retry
        </button>
      </div>
    );
  }

  // state === 'paid'
  return <>{children(content)}</>;
}
