..  include:: ../Includes.rst.txt

..  _changelog:

=========
Changelog
=========

The complete release history is kept in
`CHANGELOG.md <https://github.com/dirnbauer/typo3-x402-paywall/blob/main/CHANGELOG.md>`__.

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
