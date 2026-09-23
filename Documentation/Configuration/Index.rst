..  include:: ../Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-site-settings:

Site settings
=============

All settings live below the ``x402_paywall`` key of the site configuration.
The paywall is active when ``enabled`` is true, ``wallet_address`` and
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
    ``arbitrum`` (``eip155:42161``), ``ethereum`` (``eip155:1``). Other
    ``eip155:<chainId>`` values need :confval:`asset_address <x402-paywall-asset-address>`.
    x402 v2 always transports the CAIP-2 identifier.

..  confval:: facilitator_url
    :name: x402-paywall-facilitator-url
    :type: string
    :default: https://x402.org/facilitator

    Base URL of the facilitator; ``/verify``, ``/settle`` and ``/supported``
    are appended. The public facilitator of x402.org settles on testnets only
    (Base Sepolia, Solana devnet); the dashboard flags a mainnet network that
    points to it. For mainnet use, for example, the Coinbase Developer
    Platform facilitator ``https://api.cdp.coinbase.com/platform/v2/x402``
    together with :confval:`facilitator_auth <x402-paywall-facilitator-auth>`,
    and check its answer to ``GET /supported`` in the simulator.

..  confval:: facilitator_auth
    :name: x402-paywall-facilitator-auth
    :type: string
    :default: ""

    Authentication towards the facilitator. Empty: none (x402.org). ``cdp``:
    every request carries ``Authorization: Bearer <JWT>``, a two-minute token
    bound to the method and URL of the request and signed with a CDP secret
    API key, as the CDP SDKs issue it. ECDSA keys (PEM, ES256) and Ed25519
    keys (base64, EdDSA) are supported; Ed25519 needs the PHP extension
    ``sodium``.

..  confval:: facilitator_api_key_id
    :name: x402-paywall-facilitator-api-key-id
    :type: string
    :default: environment variable CDP_API_KEY_ID

    Id of the CDP API key. Leave it empty to read ``CDP_API_KEY_ID`` from the
    environment, or reference any variable with ``'%env(NAME)%'``.

..  confval:: facilitator_api_key_secret
    :name: x402-paywall-facilitator-api-key-secret
    :type: string
    :default: environment variable CDP_API_KEY_SECRET

    Secret of the CDP API key. Keep it out of the YAML file: leave it empty to
    read ``CDP_API_KEY_SECRET`` from the environment, or use
    ``'%env(NAME)%'``. Line breaks of a PEM key may be written as ``\n``.

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

    Decimal price used when a page has no override; converted to atomic units
    with :confval:`asset_decimals <x402-paywall-asset-decimals>`
    (``"0.01"`` becomes ``"10000"`` for USDC).

..  confval:: asset_address
    :name: x402-paywall-asset-address
    :type: string
    :default: ""

    Token contract address (``PaymentRequirements.asset``). Required for
    currencies other than USDC or networks without a known USDC deployment.

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
    still resolve to a TYPO3 page or route.

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
    kinds; disable the option once your clients moved to v2.

..  confval:: service_name
    :name: x402-paywall-service-name
    :type: string
    :default: ""

    ``resource.serviceName`` for discovery listings: printable ASCII, at most
    32 characters. Other values are ignored and reported on the dashboard.

..  confval:: service_tags
    :name: x402-paywall-service-tags
    :type: array
    :default: []

    ``resource.tags``: at most five topical tags of printable ASCII with at
    most 32 characters each.

..  confval:: service_icon_url
    :name: x402-paywall-service-icon-url
    :type: string
    :default: ""

    ``resource.iconUrl``: absolute ``http``/``https`` URL of an icon, at most
    2048 characters.

..  _configuration-page-fields:

Gating a page
=============

The :guilabel:`x402 Paywall` tab in the page properties offers:

*   :guilabel:`Sell this page with x402`: gates the page.
*   :guilabel:`Price`: overrides ``default_price``, in the currency of the
    site configuration.
*   :guilabel:`Description for the payment prompt`: becomes
    ``resource.description`` in the ``PaymentRequired`` document and the text
    on the paywall page; falls back to the page title.

Detail pages of EXT:news, EXT:blog and common event extensions are gated as
pages, but the payment log stores the record type and UID from the plugin
parameters (``tx_news_pi1[news]``, ``tx_blog_pi1[post]``,
``tx_events2_pi1[event]``, ...) so revenue can be attributed per record.
