// Components
export { PaywallOverlay } from './PaywallOverlay';
export type { PaywallOverlayProps } from './PaywallOverlay';

export { PaidContent } from './PaidContent';
export type { PaidContentProps } from './PaidContent';

// Hooks
export { useX402Paywall } from './use-x402-paywall';
export type { UseX402PaywallOptions, UseX402PaywallReturn } from './use-x402-paywall';

// Client
export { createX402Client, X402Error } from './x402-client';
export type {
  X402ClientConfig,
  X402PaymentRequirement,
  X402PaymentResponse,
} from './x402-client';

// Middleware (re-exported for convenience, main entry is /middleware)
export { withX402 } from './middleware';
export type { X402MiddlewareConfig } from './middleware';
