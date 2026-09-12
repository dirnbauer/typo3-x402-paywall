..  include:: ../Includes.rst.txt

..  _introduction:

============
Introduction
============

x402 revives the HTTP status code 402 as a machine-readable payment
handshake: the server states what it wants to be paid, the client signs a
payment authorization with a wallet, and a *facilitator* verifies the
signature and executes the transfer on-chain. No accounts, no sessions, no
card forms; a single retry of the original request settles the purchase.

..  _introduction-flow:

Protocol flow
=============

#.  ``GET /premium`` without payment: TYPO3 answers ``402 Payment Required``.
    The ``PAYMENT-REQUIRED`` header carries a base64-encoded ``PaymentRequired``
    document (``x402Version: 2``, the ``resource`` and the accepted
    ``PaymentRequirements``: scheme, CAIP-2 network, amount in atomic units,
    token contract, receiving wallet, timeout and the EIP-712 domain data).
#.  The client signs an EIP-3009 ``TransferWithAuthorization`` for the chosen
    requirement and repeats the request with the base64 ``PaymentPayload`` in
    the ``PAYMENT-SIGNATURE`` header.
#.  The middleware checks that the payload matches the offered requirement,
    asks the facilitator (``POST /verify``), produces the TYPO3 response,
    settles the payment (``POST /settle``) and adds the ``PAYMENT-RESPONSE``
    header with the ``SettlementResponse`` (transaction hash, network, payer).

Browsers that send ``Accept: text/html`` receive a wallet paywall page instead
of the JSON body; the page performs steps 2 and 3 with an EIP-1193 wallet.

..  _features:

Features
========

*   PSR-15 middleware that gates pages (page toggle or UID list) and route
    patterns.
*   Page fields for enablement, price override and payment prompt text.
*   Standalone 402 paywall page with an EIP-3009 wallet client.
*   Facilitator client for ``/verify``, ``/settle`` and ``/supported``.
*   Payment log with dashboard (revenue, top pages, recent transactions) and a
    request simulator in the backend.
*   PSR-14 events ``PaymentRequiredEvent`` and ``PaymentReceivedEvent``.
*   MCP tools ``x402_probe``, ``x402_decode_header``, ``x402_gated_pages``,
    ``x402_stats`` and ``x402_transactions`` for agent workflows.
*   Optional compatibility with x402 v1 clients (``legacy_v1``).

..  _requirements:

Requirements
============

*   TYPO3 14.3 or later, PHP 8.4 or later.
*   A wallet address that receives payments on the selected network.
*   Outbound HTTPS access from the web server to the x402 facilitator.
