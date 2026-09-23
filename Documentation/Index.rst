..  include:: Includes.rst.txt

..  _start:

==================
TYPO3 x402 Paywall
==================

:Extension key:
    |extension_key|

:Package name:
    |composer_name|

:Version:
    1.4.0

:Language:
    en

:Author:
    webconsulting

:License:
    GPL-2.0-or-later

|extension_name| gates TYPO3 pages and API routes with the x402 HTTP payment
protocol. It answers ``402 Payment Required`` with a ``PAYMENT-REQUIRED``
header, verifies and settles ``PAYMENT-SIGNATURE`` payloads through an x402
facilitator, serves the paid resource with a ``PAYMENT-RESPONSE`` header, logs
payments and offers a backend dashboard and MCP tools.

The implementation follows the |x402_spec| published by the
`x402 Foundation <https://github.com/x402-foundation/x402/tree/6fe0d4bfd104e8c61ae0b6aeaefe9da506d502ff/specs>`__
(HTTP transport v2, ``exact`` scheme on EVM networks with EIP-3009).

..  toctree::
    :maxdepth: 2

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Developer/Index
    Changelog/Index
