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
      "accepted": { "...the chosen entry of accepts..." : "" },
      "payload": {
        "signature": "0x...",
        "authorization": {"from": "0xPAYER", "to": "0xYOUR_WALLET", "value": "10000",
                          "validAfter": "1757600000", "validBefore": "1757600300", "nonce": "0x..."}
      }
    }

..  code-block:: bash
    :caption: Step 2: pay and receive the content

    curl -si -H "PAYMENT-SIGNATURE: $(cat payment-payload.b64)" https://example.com/premium
    # HTTP/2 200
    # PAYMENT-RESPONSE: base64 {"success":true,"transaction":"0x...","network":"eip155:84532","payer":"0x..."}

Rejections answer 402 again; the ``error`` field of the ``PaymentRequired``
document carries the facilitator's reason (``insufficient_funds``,
``invalid_exact_evm_payload_signature``, ``invalid_payment_requirements``,
...). Requests whose TYPO3 response is not 2xx are never settled.

Any x402 v2 client library works, for example the JavaScript packages
published in the `coinbase/x402 <https://github.com/coinbase/x402>`__
repository.

..  _usage-browser:

Browsers
========

Requests with ``Accept: text/html`` receive a standalone 402 page rendered
from :file:`Resources/Private/Templates/Paywall/PaymentRequired.html`. Its
script (:file:`Resources/Public/JavaScript/paywall.js`) connects an EIP-1193
wallet (MetaMask, Coinbase Wallet, Rabby, ...), switches to the required
chain, signs the authorization, retries the page with ``PAYMENT-SIGNATURE``
and replaces the document with the paid response. Override the template path
or the CSS custom properties in :file:`Resources/Public/Css/paywall.css` to
brand the page.

..  _usage-dashboard:

Backend module
==============

:guilabel:`Web > x402 Paywall` (admin only) shows revenue for today, seven
days, thirty days and all time, the top pages, the most recent transactions
and a simulator that sends a GET (optionally with a syntactically valid but
unsigned ``PAYMENT-SIGNATURE``) to a public URL and decodes the answer. The
``Facilitator /supported`` scenario lists the kinds the facilitator settles.

..  _usage-legacy:

x402 v1 clients
===============

With ``legacy_v1: true`` the middleware additionally accepts the v1
``X-PAYMENT`` header, returns the v1 JSON body and mirrors the settlement
into ``X-PAYMENT-RESPONSE``. See :confval:`legacy_v1 <x402-paywall-legacy-v1>`.
