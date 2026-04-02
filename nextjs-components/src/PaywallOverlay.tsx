'use client';

import React from 'react';

export interface PaywallOverlayProps {
  /** Price string (e.g., "0.01 USDC") */
  price: string;
  /** Content description */
  description: string;
  /** Network name (e.g., "base-sepolia") */
  network: string;
  /** Current state */
  state: 'payment_required' | 'paying' | 'error';
  /** Error message */
  error?: string | null;
  /** Called when user clicks "Pay" */
  onPay: () => void;
  /** Called when user clicks "Cancel" */
  onCancel?: () => void;
  /** Preview content to show above the paywall */
  children?: React.ReactNode;
  /** Custom CSS class */
  className?: string;
}

/**
 * Paywall overlay component for gated TYPO3 content.
 *
 * Shows a payment prompt with price, description, and pay/cancel buttons.
 * Renders children (preview content) with a gradient fade-out below.
 */
export function PaywallOverlay({
  price,
  description,
  network,
  state,
  error,
  onPay,
  onCancel,
  children,
  className = '',
}: PaywallOverlayProps) {
  return (
    <div className={`x402-paywall-container ${className}`} style={{ position: 'relative' }}>
      {/* Preview content with fade-out */}
      {children && (
        <div style={{ position: 'relative', maxHeight: '300px', overflow: 'hidden' }}>
          {children}
          <div
            style={{
              position: 'absolute',
              bottom: 0,
              left: 0,
              right: 0,
              height: '120px',
              background: 'linear-gradient(transparent, var(--x402-bg, white))',
              pointerEvents: 'none',
            }}
          />
        </div>
      )}

      {/* Paywall card */}
      <div
        className="x402-paywall-card"
        style={{
          border: '1px solid var(--x402-border, #e5e7eb)',
          borderRadius: '12px',
          padding: '24px',
          textAlign: 'center',
          background: 'var(--x402-card-bg, #f9fafb)',
          maxWidth: '480px',
          margin: '24px auto',
        }}
      >
        {/* Lock icon */}
        <div style={{ fontSize: '32px', marginBottom: '12px' }}>🔒</div>

        <h3 style={{ margin: '0 0 8px', fontSize: '18px', fontWeight: 600 }}>
          Premium Content
        </h3>

        <p style={{ color: 'var(--x402-muted, #6b7280)', margin: '0 0 16px', fontSize: '14px' }}>
          {description}
        </p>

        {/* Price badge */}
        <div
          style={{
            display: 'inline-block',
            padding: '8px 20px',
            borderRadius: '20px',
            background: 'var(--x402-accent-bg, #ecfdf5)',
            color: 'var(--x402-accent, #059669)',
            fontWeight: 600,
            fontSize: '16px',
            marginBottom: '16px',
          }}
        >
          {price}
        </div>

        <div style={{ fontSize: '12px', color: 'var(--x402-muted, #9ca3af)', marginBottom: '16px' }}>
          via x402 on {network}
        </div>

        {error && (
          <div
            style={{
              background: '#fef2f2',
              border: '1px solid #fecaca',
              color: '#dc2626',
              padding: '8px 12px',
              borderRadius: '8px',
              fontSize: '13px',
              marginBottom: '12px',
            }}
          >
            {error}
          </div>
        )}

        {/* Action buttons */}
        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
          <button
            onClick={onPay}
            disabled={state === 'paying'}
            style={{
              padding: '10px 24px',
              borderRadius: '8px',
              border: 'none',
              background: 'var(--x402-primary, #1b7a95)',
              color: 'white',
              fontWeight: 600,
              fontSize: '14px',
              cursor: state === 'paying' ? 'wait' : 'pointer',
              opacity: state === 'paying' ? 0.7 : 1,
              transition: 'opacity 0.2s',
            }}
          >
            {state === 'paying' ? 'Processing...' : `Pay ${price}`}
          </button>

          {onCancel && (
            <button
              onClick={onCancel}
              disabled={state === 'paying'}
              style={{
                padding: '10px 24px',
                borderRadius: '8px',
                border: '1px solid var(--x402-border, #d1d5db)',
                background: 'transparent',
                color: 'var(--x402-muted, #6b7280)',
                fontWeight: 500,
                fontSize: '14px',
                cursor: 'pointer',
              }}
            >
              Cancel
            </button>
          )}
        </div>

        <p style={{ fontSize: '11px', color: 'var(--x402-muted, #9ca3af)', marginTop: '12px', marginBottom: 0 }}>
          Powered by <a href="https://www.x402.org" target="_blank" rel="noopener" style={{ color: 'inherit' }}>x402</a>
        </p>
      </div>
    </div>
  );
}
