/**
 * Next.js middleware helper for x402 payment verification.
 *
 * Use this in your Next.js middleware.ts to gate routes
 * before they reach your page components.
 *
 * Usage in middleware.ts:
 * ```ts
 * import { withX402 } from '@webconsulting/typo3-x402-react/middleware';
 *
 * export default withX402({
 *   typo3ApiUrl: process.env.TYPO3_API_URL!,
 *   gatedPaths: ['/premium/*', '/articles/*/full'],
 * });
 * ```
 */

import { NextRequest, NextResponse } from 'next/server';

export interface X402MiddlewareConfig {
  /** TYPO3 headless API URL */
  typo3ApiUrl: string;
  /** Paths that require x402 payment (supports glob patterns) */
  gatedPaths: string[];
  /** Paths that are always free */
  freePaths?: string[];
}

/**
 * Create a Next.js middleware that proxies x402 payment requirements from TYPO3.
 */
export function withX402(config: X402MiddlewareConfig) {
  return async function middleware(request: NextRequest): Promise<NextResponse> {
    const path = request.nextUrl.pathname;

    // Check if path is free
    if (config.freePaths?.some((p) => matchPath(path, p))) {
      return NextResponse.next();
    }

    // Check if path is gated
    if (!config.gatedPaths.some((p) => matchPath(path, p))) {
      return NextResponse.next();
    }

    // Check for x402 payment header
    const paymentSignature = request.headers.get('PAYMENT-SIGNATURE');

    // Proxy the request to TYPO3 with the payment header (if present)
    const typo3Url = `${config.typo3ApiUrl}${path}`;
    const headers: HeadersInit = {
      Accept: 'application/json',
    };

    if (paymentSignature) {
      headers['PAYMENT-SIGNATURE'] = paymentSignature;
    }

    try {
      const typo3Response = await fetch(typo3Url, { headers });

      if (typo3Response.status === 402) {
        // Forward the 402 with payment requirements
        const body = await typo3Response.json();
        const paymentRequired = typo3Response.headers.get('PAYMENT-REQUIRED');

        const response = NextResponse.json(body, { status: 402 });
        if (paymentRequired) {
          response.headers.set('PAYMENT-REQUIRED', paymentRequired);
        }
        return response;
      }

      // Payment accepted or not required — continue to Next.js page
      return NextResponse.next();
    } catch {
      // TYPO3 unreachable — let the request through (fail-open)
      return NextResponse.next();
    }
  };
}

function matchPath(path: string, pattern: string): boolean {
  if (path === pattern) return true;
  if (pattern.endsWith('/*')) {
    return path.startsWith(pattern.slice(0, -1));
  }
  // Simple glob: convert * to regex
  const regex = new RegExp(
    '^' + pattern.replace(/\*/g, '[^/]*') + '$'
  );
  return regex.test(path);
}
