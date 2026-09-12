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
    1.2.0

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
settled payments and exposes a backend dashboard and MCP tools.

The implementation follows the |x402_spec| published in the
`coinbase/x402 <https://github.com/coinbase/x402/tree/main/specs>`__
repository (HTTP transport v2, ``exact`` scheme on EVM networks).

..  important::

    This release requires TYPO3 14.3+ and PHP 8.4+.

..  toctree::
    :maxdepth: 2

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Developer/Index
    Security/Index
