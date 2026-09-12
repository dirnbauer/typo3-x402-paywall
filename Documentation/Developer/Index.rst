..  include:: ../Includes.rst.txt

..  _developer:

=========
Developer
=========

..  _developer-services:

Services
========

All classes are registered in :file:`Configuration/Services.yaml` and can be
injected:

:php:`Webconsulting\X402Paywall\Configuration\ConfigurationProvider`
    Reads the ``x402_paywall`` block of the current site into a
    :php:`PaywallConfiguration` value object.

:php:`Webconsulting\X402Paywall\Service\RouteGateResolver`
    Decides whether a request is gated and resolves price and description.

:php:`Webconsulting\X402Paywall\Service\PaymentVerifier`
    Facilitator client: :php:`verify()`, :php:`settle()`, :php:`supported()`
    and :php:`supportsRequirement()`.

:php:`Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory`
    Builds 402 responses (JSON or the HTML paywall page) with the
    ``PAYMENT-REQUIRED`` header.

:php:`Webconsulting\X402Paywall\Service\PaymentLogger`
    Writes :sql:`tx_x402_payment_log` and provides the dashboard queries.

Domain models in :php:`Webconsulting\X402Paywall\Domain\Model` mirror the
specification: :php:`PaymentRequired` (header document), :php:`ResourceInfo`,
:php:`PaymentRequirement` and :php:`PaymentPayload`. All of them offer
:php:`fromArray()` / :php:`toArray()` and header encoding helpers.

..  _developer-events:

Events
======

..  php:class:: Webconsulting\X402Paywall\Event\PaymentRequiredEvent

    Dispatched before a 402 response is returned. Properties:
    ``requestUri``, ``price``, ``currency``, ``network`` (CAIP-2).

..  php:class:: Webconsulting\X402Paywall\Event\PaymentReceivedEvent

    Dispatched after a payment was verified and settled and the resource is
    served. Properties: ``requestUri``, ``price``, ``currency``, ``txHash``,
    ``network``, ``payer``.

..  _developer-middleware:

Middleware position
===================

:php:`Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware` runs in the
frontend stack after ``typo3/cms-frontend/prepare-tsfe-rendering`` and
``shortcut-and-mountpoint-redirect`` (so the resolved page record is
available) and before ``csp-headers``. Settlement is only attempted when the
inner handler produced a 2xx response.

..  _developer-mcp-tools:

MCP tools
=========

Tools are tagged ``mcp.tool`` and picked up by ``hn/typo3-mcp-server``. They
return plain strings (JSON), the server adapter wraps them into the installed
SDK's result type.

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
            values.

    -   :Tool: ``x402_gated_pages``
        :Input: none
        :Purpose: Pages with the paywall toggle, price override and prompt.

    -   :Tool: ``x402_stats``
        :Input: ``period`` (today, 7days, 30days, all)
        :Purpose: Settled revenue, transaction count and top pages.

    -   :Tool: ``x402_transactions``
        :Input: ``limit`` (1-50)
        :Purpose: Recent log entries with status, payer and transaction hash.

..  _developer-quality-gates:

Quality gates
=============

..  code-block:: bash
    :caption: Local checks (PHP 8.4+, sqlite for functional tests)

    composer install
    composer validate --strict && composer audit
    composer ci                                   # lint, cgl, phpstan (max), unit, functional
    Build/Scripts/runTests.sh -s functional -d mariadb

Unit tests live in :file:`tests/Unit`, the functional test in
:file:`tests/Functional` runs the real frontend middleware stack against a
gated page. GitHub Actions executes the same suites on PHP 8.4 (8.5 as
allowed failure) with sqlite and MariaDB 10.11.
