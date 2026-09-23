..  include:: ../Includes.rst.txt

..  _introduction:

============
Introduction
============

x402 revives HTTP status 402 as a machine-readable payment handshake: the
server states what it wants to be paid, the client signs a payment
authorization with a wallet, and a *facilitator* verifies the signature and
executes the transfer on-chain. A single retry of the original request settles
the purchase.

..  _introduction-flow:

Protocol flow
=============

#.  ``GET /premium`` without payment: TYPO3 answers ``402 Payment Required``.
    The ``PAYMENT-REQUIRED`` header carries a base64-encoded ``PaymentRequired``
    document: ``x402Version: 2``, the ``resource`` (URL, description, MIME
    type) and the accepted ``PaymentRequirements`` (scheme ``exact``, CAIP-2
    network, amount in atomic units, token contract, receiving wallet, timeout
    and the EIP-712 domain of the token in ``extra``).
#.  The client signs an EIP-3009 ``TransferWithAuthorization`` for the chosen
    requirement and repeats the request with the base64 ``PaymentPayload`` in
    the ``PAYMENT-SIGNATURE`` header.
#.  The middleware checks that the payload repeats the offered requirement,
    asks the facilitator (``POST /verify``), produces the TYPO3 response,
    settles the payment (``POST /settle``) and adds the ``PAYMENT-RESPONSE``
    header with the ``SettlementResponse`` (transaction hash, network, payer).
    If the settlement fails, the answer is a 402 whose ``PAYMENT-RESPONSE``
    says why.

Browsers that send ``Accept: text/html`` receive a wallet paywall page instead
of the JSON body; the page performs steps 2 and 3 with an EIP-1193 wallet.

..  _features:

Features
========

*   PSR-15 middleware gating pages (toggle or UID list) and route patterns.
*   Page fields for enablement, price override and payment prompt.
*   Standalone 402 paywall page with an EIP-3009 wallet client.
*   Payment log with a dashboard (revenue per currency, settlement states,
    configuration check per site) and a payment flow simulator in the backend.
*   Facilitators: x402.org (testnets) and any compatible facilitator, including
    Coinbase CDP with signed API-key tokens.
*   Discovery metadata (``serviceName``, ``tags``, ``iconUrl``) per site.
*   PSR-14 events ``PaymentRequiredEvent`` and ``PaymentReceivedEvent``.
*   MCP tools ``x402_probe``, ``x402_decode_header``, ``x402_gated_pages``,
    ``x402_stats`` and ``x402_transactions``.
*   Optional x402 v1 compatibility (``legacy_v1``).

..  _requirements:

Requirements
============

*   TYPO3 14.3 or later, PHP 8.4 or later, Composer mode.
*   A wallet address that receives payments on the selected network.
*   Outbound HTTPS from the web server to the x402 facilitator.
