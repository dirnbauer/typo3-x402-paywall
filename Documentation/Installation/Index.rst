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
:sql:`tx_x402_payment_log`. The extension ships no ``ext_emconf.php`` and no
TypoScript; the middleware, TCA, backend module and MCP tools register through
:file:`Configuration/`.

..  _installation-setup:

Basic setup
===========

#.  Add the :ref:`x402_paywall site settings <configuration-site-settings>`
    to :file:`config/sites/<identifier>/config.yaml`.
#.  Enable the :guilabel:`x402 Paywall` tab on the pages that should require
    payment, or list route patterns for API responses.
#.  Open :guilabel:`Web > x402 Paywall` and run the simulator against a
    public URL of your site to see the 402 response and the decoded
    ``PAYMENT-REQUIRED`` header.

..  tip::

    Start on ``base-sepolia`` with testnet USDC before switching to a
    production network. ``GET <facilitator_url>/supported`` lists the
    (``x402Version``, ``scheme``, ``network``) combinations the facilitator
    settles.

..  _installation-upgrade:

Upgrading from 1.x
==================

Version 1.2.0 speaks x402 v2 on the wire and no longer ships the Extbase
overlay plugin. After the update:

*   Remove content elements of the former plugin ``x402paywall_paywall``; the
    middleware now renders the paywall page itself.
*   Drop the site settings ``pricing_mode`` and ``free_preview_paragraphs``.
*   Clients that still send ``X-PAYMENT`` (x402 v1) only work with
    ``legacy_v1: true``.
