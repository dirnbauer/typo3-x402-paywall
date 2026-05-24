..  include:: ../Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-site-settings:

Site settings
=============

The extension reads settings from the current TYPO3 site configuration below
the ``x402_paywall`` key.

..  literalinclude:: _site-config.example.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

..  confval:: x402_paywall.enabled
    :name: x402-paywall-enabled
    :type: bool
    :default: false

    Enables the paywall for the current site.

..  confval:: x402_paywall.wallet_address
    :name: x402-paywall-wallet-address
    :type: string
    :default: ""

    Wallet address that receives payments.

..  confval:: x402_paywall.network
    :name: x402-paywall-network
    :type: string
    :default: base-sepolia

    Supported values are ``base``, ``base-sepolia``, ``polygon``, and
    ``ethereum``.

..  confval:: x402_paywall.facilitator_url
    :name: x402-paywall-facilitator-url
    :type: string
    :default: https://x402.org/facilitator

    x402 facilitator endpoint used for verification and settlement.

..  confval:: x402_paywall.currency
    :name: x402-paywall-currency
    :type: string
    :default: USDC

    Payment currency shown in payment requirements and backend analytics.

..  confval:: x402_paywall.default_price
    :name: x402-paywall-default-price
    :type: string
    :default: 0.01

    Default price in the configured currency.

..  confval:: x402_paywall.pricing_mode
    :name: x402-paywall-pricing-mode
    :type: string
    :default: per-request

    Currently stored for integrator use. Supported values are ``per-request``
    and ``per-page``.

..  confval:: x402_paywall.free_preview_paragraphs
    :name: x402-paywall-free-preview-paragraphs
    :type: int
    :default: 0

    Number of preview paragraphs assigned to the frontend view.

..  confval:: x402_paywall.free_routes
    :name: x402-paywall-free-routes
    :type: array
    :default: []

    Route patterns that always stay free.

..  confval:: x402_paywall.gated_route_patterns
    :name: x402-paywall-gated-route-patterns
    :type: array
    :default: []

    Route patterns that require payment. A trailing ``*`` works as wildcard.

..  confval:: x402_paywall.gated_page_uids
    :name: x402-paywall-gated-page-uids
    :type: array
    :default: []

    Page UIDs that require payment without using the page toggle.

..  _configuration-page-fields:

Page fields
===========

The extension adds an :guilabel:`x402 Paywall` tab to page properties:

* :guilabel:`Enable x402 paywall` gates the page.
* :guilabel:`Price (USDC)` overrides the site default price.
* :guilabel:`Content description for payment prompt` is shown in the payment
  requirement and frontend overlay.
