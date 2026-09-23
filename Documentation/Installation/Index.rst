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
#.  Open :guilabel:`Content > x402 Paywall > Dashboard` and fix the
    configuration problems it lists, if any.
#.  Open :guilabel:`Content > x402 Paywall > Simulator` and run *Request
    without payment* against your site to see the 402 response and the
    decoded ``PAYMENT-REQUIRED`` header.

..  tip::

    Start on ``base-sepolia`` with testnet USDC before switching to a
    production network. The public x402.org facilitator settles on testnets
    only; for mainnet configure a facilitator such as Coinbase CDP
    (:confval:`facilitator_auth <x402-paywall-facilitator-auth>`). The
    *Facilitator capabilities* scenario lists the (``x402Version``,
    ``scheme``, ``network``) kinds the facilitator settles.

..  _installation-upgrade:

Upgrading
=========

Version 1.4.0 needs no database update: the payment log gains the status
value ``pending`` in the existing column. The backend module became
:guilabel:`Content > x402 Paywall` with the submodules :guilabel:`Dashboard`
and :guilabel:`Simulator`; its path ``/module/web/x402-paywall`` still opens
the dashboard, but the old route identifiers ``web_x402_paywall.simulator``
and ``web_x402_paywall.runSimulation`` are gone. A settlement failure now
answers 402 with ``PAYMENT-RESPONSE`` instead of ``PAYMENT-REQUIRED``, and
unreachable facilitators are reported with the specification codes
``unexpected_verify_error`` / ``unexpected_settle_error``. New site settings
are optional (see :ref:`changelog`).

Upgrading from 1.1 or earlier: remove content elements of the former plugin
``x402paywall_paywall`` and the settings ``pricing_mode`` and
``free_preview_paragraphs``; clients that still send ``X-PAYMENT`` need
``legacy_v1: true``.
