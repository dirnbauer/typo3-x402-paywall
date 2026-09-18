..  include:: ../Includes.rst.txt

..  _installation:

============
Installation
============

..  code-block:: bash
    :caption: Install with Composer

    composer require webconsulting/typo3-x402-paywall
    vendor/bin/typo3 database:updateschema

The schema update adds three columns to :sql:`pages`
(``tx_x402_paywall_enabled``, ``tx_x402_paywall_price``,
``tx_x402_paywall_description``) and the log table
:sql:`tx_x402_payment_log`. Middleware, TCA, backend module and MCP tools
register through :file:`Configuration/`; there is no TypoScript to include.

..  _installation-setup:

First steps
===========

#.  Add the :ref:`x402_paywall site settings <configuration-site-settings>`
    to :file:`config/sites/<identifier>/config.yaml`.
#.  Enable the :guilabel:`x402 Paywall` tab on the pages that should require
    payment, or list route patterns for API responses.
#.  Open :guilabel:`Web > x402 Paywall > Simulator` and run the *Plain GET*
    scenario against a gated URL of your site to see the 402 response and the
    decoded ``PAYMENT-REQUIRED`` header.

..  tip::

    Start on ``base-sepolia`` with testnet USDC before switching to a
    production network. The *Facilitator /supported* scenario lists the
    (``x402Version``, ``scheme``, ``network``) kinds the facilitator settles.

..  _installation-upgrade:

Upgrading
=========

Version 1.3.0 keeps the wire format of 1.2.0. Internal classes changed (see
:ref:`changelog`); site settings, page fields, events and MCP tools are
unchanged. Upgrading from 1.1 or earlier: remove content elements of the former
plugin ``x402paywall_paywall`` and the settings ``pricing_mode`` and
``free_preview_paragraphs``; clients that still send ``X-PAYMENT`` need
``legacy_v1: true``.
