..  include:: ../Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-site-settings:

Site settings
=============

All settings live below the ``x402_paywall`` key of the site configuration.
The paywall is only active when ``enabled`` is true, ``wallet_address`` and
``facilitator_url`` are set and the payment asset is known for the network.

..  literalinclude:: _site-config.example.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

..  confval:: enabled
    :name: x402-paywall-enabled
    :type: bool
    :default: false

    Enables the paywall for the site.

..  confval:: wallet_address
    :name: x402-paywall-wallet-address
    :type: string
    :default: ""

    Address that receives payments (``PaymentRequirements.payTo``).

..  confval:: network
    :name: x402-paywall-network
    :type: string
    :default: base-sepolia

    Network alias or CAIP-2 identifier. Aliases: ``base`` (``eip155:8453``),
    ``base-sepolia`` (``eip155:84532``), ``polygon`` (``eip155:137``),
    ``arbitrum`` (``eip155:42161``), ``ethereum`` (``eip155:1``). Any
    ``eip155:<chainId>`` value is accepted; combine it with
    :confval:`asset_address <x402-paywall-asset-address>`. x402 v2 always
    transports the CAIP-2 identifier.

..  confval:: facilitator_url
    :name: x402-paywall-facilitator-url
    :type: string
    :default: https://x402.org/facilitator

    Base URL of the facilitator. ``/verify``, ``/settle`` and ``/supported``
    are appended.

..  confval:: currency
    :name: x402-paywall-currency
    :type: string
    :default: USDC

    Label shown to users and stored in the payment log. For ``USDC`` the token
    contract of the well-known networks is filled in automatically.

..  confval:: default_price
    :name: x402-paywall-default-price
    :type: string
    :default: "0.01"

    Decimal price used when a page has no override. It is converted to atomic
    units with :confval:`asset_decimals <x402-paywall-asset-decimals>`
    (``"0.01"`` becomes ``"10000"`` for USDC).

..  confval:: asset_address
    :name: x402-paywall-asset-address
    :type: string
    :default: ""

    Token contract address (``PaymentRequirements.asset``). Required for
    currencies other than USDC or for networks without a known USDC
    deployment.

..  confval:: asset_decimals
    :name: x402-paywall-asset-decimals
    :type: int
    :default: 6

    Decimals of the token.

..  confval:: asset_name
    :name: x402-paywall-asset-name
    :type: string
    :default: (from network table)

    EIP-712 domain name of the token (``extra.name``), e.g. ``USD Coin`` on
    Base mainnet and ``USDC`` on Base Sepolia. Wallets need the exact value to
    produce a valid ``TransferWithAuthorization`` signature.

..  confval:: asset_version
    :name: x402-paywall-asset-version
    :type: string
    :default: "2"

    EIP-712 domain version of the token (``extra.version``).

..  confval:: max_timeout_seconds
    :name: x402-paywall-max-timeout-seconds
    :type: int
    :default: 300

    ``PaymentRequirements.maxTimeoutSeconds``: how long a signed authorization
    may be settled.

..  confval:: free_routes
    :name: x402-paywall-free-routes
    :type: array
    :default: []

    Paths that never require payment. Exact paths, ``/prefix/*`` and glob
    patterns (``fnmatch``) are supported; free routes take precedence.

..  confval:: gated_route_patterns
    :name: x402-paywall-gated-route-patterns
    :type: array
    :default: []

    Paths that require payment, same syntax as ``free_routes``. The path must
    still resolve to a TYPO3 page or route, otherwise the page resolver
    answers 404 first.

..  confval:: gated_page_uids
    :name: x402-paywall-gated-page-uids
    :type: array
    :default: []

    Page UIDs that require payment without the page toggle.

..  confval:: legacy_v1
    :name: x402-paywall-legacy-v1
    :type: bool
    :default: false

    Accept x402 v1 clients in addition to v2: the ``X-PAYMENT`` request header
    is read, the 402 JSON body uses the v1 ``PaymentRequirementsResponse``
    shape (``maxAmountRequired``, alias network names) and settlements are
    mirrored into ``X-PAYMENT-RESPONSE``. The ``PAYMENT-REQUIRED`` header
    always stays v2. The public facilitator still lists ``x402Version: 1``
    kinds, which is why the option exists; disable it once your clients moved
    to v2.

..  _configuration-page-fields:

Page fields
===========

The :guilabel:`x402 Paywall` tab in the page properties offers:

*   :guilabel:`Enable x402 paywall`: gates the page.
*   :guilabel:`Price (USDC)`: overrides ``default_price``.
*   :guilabel:`Content description for payment prompt`: becomes
    ``resource.description`` in the ``PaymentRequired`` document and the text
    on the paywall page; falls back to the page title.

..  _configuration-records:

Detail records
==============

Detail pages of EXT:news, EXT:blog and common event extensions are gated as
pages, but the payment log stores the record type and UID taken from the
plugin parameters (``tx_news_pi1[news]``, ``tx_blog_pi1[post]``,
``tx_events2_pi1[event]``, ...), so revenue can be attributed per record.
