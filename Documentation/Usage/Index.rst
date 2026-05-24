..  include:: ../Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-frontend:

Traditional frontend pages
==========================

Enable the paywall on a TYPO3 page and add the :guilabel:`x402 paywall
overlay` plugin to the page content. The plugin renders the payment prompt only
when the current page is gated and the site configuration is valid.

The frontend JavaScript expects an EIP-1193 wallet provider such as MetaMask,
Coinbase Wallet, or Rabby.

..  _usage-headless:

Headless routes
===============

For headless or API content, configure ``gated_route_patterns``.

..  code-block:: yaml
    :caption: Gated API route example

    x402_paywall:
        enabled: true
        wallet_address: "0xYOUR_WALLET_ADDRESS"
        gated_route_patterns:
            - "/api/v1/content/*"
        free_routes:
            - "/api/v1/public/*"

Requests without a ``PAYMENT-SIGNATURE`` header receive status 402 and a
``PAYMENT-REQUIRED`` header. Requests with a valid payment signature are settled
and passed to the normal TYPO3 request handler.

..  _usage-dashboard:

Backend dashboard
=================

Administrators can open :guilabel:`Web > x402 Paywall` to see:

* Revenue for today, seven days, thirty days, and all time.
* Top monetized pages for the last thirty days.
* Recent payment transactions.
* A simulator for public HTTP(S) URLs.

..  note::

    The simulator and MCP probe tool reject local, private, and reserved
    network targets before making server-side requests.
