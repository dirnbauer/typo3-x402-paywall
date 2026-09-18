..  include:: ../Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-api:

API and agent clients
=====================

A request without payment receives the ``PaymentRequired`` document in the
``PAYMENT-REQUIRED`` header and, for non-browser clients, as JSON body:

..  code-block:: bash
    :caption: Step 1: 402 Payment Required

    curl -si -H 'Accept: application/json' https://example.com/premium

..  code-block:: json
    :caption: Decoded PAYMENT-REQUIRED header

    {
      "x402Version": 2,
      "resource": {"url": "https://example.com/premium", "description": "Premium article", "mimeType": "application/json"},
      "accepts": [{
        "scheme": "exact",
        "network": "eip155:84532",
        "amount": "10000",
        "asset": "0x036CbD53842c5426634e7929541eC2318f3dCF7e",
        "payTo": "0xYOUR_WALLET",
        "maxTimeoutSeconds": 300,
        "extra": {"name": "USDC", "version": "2"}
      }]
    }

The client signs an EIP-3009 ``TransferWithAuthorization`` (domain: ``name``
and ``version`` from ``extra``, ``chainId`` from the CAIP-2 network,
``verifyingContract`` = ``asset``) and retries with the ``PaymentPayload``:

..  code-block:: json
    :caption: PaymentPayload, base64-encoded into PAYMENT-SIGNATURE

    {
      "x402Version": 2,
      "resource": {"url": "https://example.com/premium"},
      "accepted": {"scheme": "exact", "network": "eip155:84532", "amount": "10000", "asset": "0x036C…", "payTo": "0xYOUR_WALLET", "maxTimeoutSeconds": 300},
      "payload": {
        "signature": "0x…",
        "authorization": {"from": "0xPAYER", "to": "0xYOUR_WALLET", "value": "10000",
                          "validAfter": "1757600000", "validBefore": "1757600300", "nonce": "0x…"}
      }
    }

..  code-block:: bash
    :caption: Step 2: pay and receive the content

    curl -si -H "PAYMENT-SIGNATURE: $(cat payment-payload.b64)" https://example.com/premium
    # HTTP/2 200
    # PAYMENT-RESPONSE: base64 {"success":true,"transaction":"0x…","network":"eip155:84532","payer":"0x…"}

Rejections answer 402 again; the ``error`` field of the ``PaymentRequired``
document carries the reason (``invalid_payload``, ``invalid_x402_version``,
``invalid_payment_requirements`` from TYPO3; ``insufficient_funds``,
``invalid_exact_evm_payload_signature``, ... from the facilitator). Requests
whose TYPO3 response is not 2xx are never settled. Any x402 v2 client works,
for example the packages published in the
`coinbase/x402 <https://github.com/coinbase/x402>`__ repository.

..  _usage-browser:

Browsers
========

Requests with ``Accept: text/html`` receive a standalone 402 page rendered
from :file:`Resources/Private/Templates/Paywall/PaymentRequired.html`. Its
script (:file:`Resources/Public/JavaScript/paywall.js`) connects an EIP-1193
wallet, switches to the required chain, signs the authorization, retries the
page with ``PAYMENT-SIGNATURE`` and replaces the document with the paid
response. Brand the page through the CSS custom properties in
:file:`Resources/Public/Css/paywall.css` or by overriding the template.

..  _usage-dashboard:

Backend module
==============

:guilabel:`Web > x402 Paywall` (admin only) shows revenue for today, seven
days, thirty days and all time, the top pages and the most recent
transactions. The :guilabel:`Simulator` sends a GET to a public URL and shows
status, headers, body and the decoded ``PAYMENT-REQUIRED`` header. The *Mock
signature* scenario answers the 402 like a client would, with a syntactically
valid ``PaymentPayload`` for the offered requirement and a dummy signature:
TYPO3 forwards it to the facilitator, which rejects it, proving that the
verification path is wired.

..  _usage-demo:

Demo flow on Base Sepolia
=========================

#.  Configure the site with ``network: base-sepolia``, your wallet and the
    public facilitator (see :ref:`configuration-site-settings`), enable the
    toggle on a page and set a price such as ``0.05``.
#.  Run the *Plain GET* and *Mock signature* simulator scenarios against the
    page URL: the first returns 402 with the decoded requirement, the second a
    402 whose ``error`` names the facilitator's rejection.
#.  Fund a browser wallet with Base Sepolia ETH (gas is paid by the
    facilitator, but the wallet needs an account) and testnet USDC from the
    `Circle faucet <https://faucet.circle.com>`__, open the page, click
    :guilabel:`Pay`, sign the ``TransferWithAuthorization``: the page reloads
    with the content and the transaction appears in the dashboard.
#.  Ask an agent: ``x402_probe {"url": "https://example.com/premium"}`` returns
    the decoded requirement, ``x402_transactions {"limit": 5}`` the log entry.

..  _usage-mcp:

MCP tools
=========

The tools are tagged ``mcp.tool`` and served by ``hn/typo3-mcp-server``; they
return JSON strings and never write data.

..  t3-field-list-table::
    :header-rows: 1

    -   :Tool: Tool
        :Input: Input
        :Purpose: Purpose

    -   :Tool: ``x402_probe``
        :Input: ``url``
        :Purpose: GET a public URL and decode a 402 (v2 header; v1 bodies are
            flagged ``legacy``). Private and loopback targets are refused.

    -   :Tool: ``x402_decode_header``
        :Input: ``header``, ``decimals`` (default 6)
        :Purpose: Decode and explain ``PAYMENT-REQUIRED``,
            ``PAYMENT-SIGNATURE`` / ``X-PAYMENT`` or ``PAYMENT-RESPONSE``
            values (kind, price, network, asset, payTo, payer, transaction).

    -   :Tool: ``x402_gated_pages``
        :Input: none
        :Purpose: Pages with the paywall toggle, price override and prompt.

    -   :Tool: ``x402_stats``
        :Input: ``period`` (today, 7days, 30days, all)
        :Purpose: Settled revenue, transaction count and top pages.

    -   :Tool: ``x402_transactions``
        :Input: ``limit`` (1-50)
        :Purpose: Recent log entries with status, payer and transaction hash.
