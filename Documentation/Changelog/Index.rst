..  include:: ../Includes.rst.txt

..  _changelog:

=========
Changelog
=========

The complete release history is kept in
`CHANGELOG.md <https://github.com/dirnbauer/typo3-x402-paywall/blob/main/CHANGELOG.md>`__.

1.4.0
=====

*   Protocol re-checked against the x402 specification v2 of the x402
    Foundation (``x402-foundation/x402`` at ``6fe0d4b``): failed settlements
    answer 402 with ``PAYMENT-RESPONSE``; ``settlement_pending`` is retried
    once and logged as *pending*; payloads are forwarded byte for byte;
    ``accepted`` must repeat ``maxTimeoutSeconds`` and ``extra``;
    ``assetTransferMethod`` / ``paymentFlow`` values other than
    ``eip3009`` / ``authorization`` are refused; ``resource.mimeType``
    describes the resource; ``serviceName``, ``tags`` and ``iconUrl``;
    ``errorMessage``, ``invalidMessage`` and settlement ``extensions`` pass
    through; specification error codes for unreachable facilitators.
*   Coinbase CDP facilitator: ``facilitator_auth: cdp`` signs every request
    with a CDP API key (ECDSA or Ed25519).
*   Backend module rebuilt with native TYPO3 v14 components:
    :guilabel:`Content > x402 Paywall` with :guilabel:`Dashboard` (revenue per
    currency, pending warning, configuration check per site, block explorer
    links) and :guilabel:`Simulator` (real targets of the site, decoded
    headers, facilitator capabilities).
*   PHP 8.4 idioms, typed constants, PHPStan level 8, CI on PHP 8.4 and 8.5.

1.3.0
=====

*   Wire format re-verified against x402 specification v2.0 (2025-12-09), the
    HTTP transport v2 and the live facilitator; ``PaymentRequired.extensions``
    is passed through, client payloads are forwarded verbatim.
*   Typed facilitator results (``VerifyResponse``, ``SettlementResponse``);
    x402 v1 handling isolated in ``Legacy\X402V1``; ``ReportingPeriod`` enum.
*   Removed unused code: ``RequestAttributeResolver``, the ``statsAction``
    AJAX route, ``PaymentVerifier::supported()`` / ``supportsRequirement()``
    / ``testConnection()``, ``PaywallConfiguration::getChainId()``, the
    pre-1.2.0 object-asset shim and fabricated simulator steps.
*   Simulator mock signature is built from the offered requirement, so the
    facilitator rejection is real.
*   PHPStan level 8, 99 unit and 8 functional tests, refreshed dependencies.

1.2.0
=====

x402 v2 wire format (``PAYMENT-REQUIRED`` / ``PAYMENT-SIGNATURE`` /
``PAYMENT-RESPONSE``), browser paywall page, ``legacy_v1`` option, functional
test suite.

1.1.0
=====

SDK-neutral MCP tools.

1.0.0
=====

Initial release.
