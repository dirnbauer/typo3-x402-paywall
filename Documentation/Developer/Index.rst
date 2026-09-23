..  include:: ../Includes.rst.txt

..  _developer:

=========
Developer
=========

..  _developer-services:

Services and models
===================

All classes are registered in :file:`Configuration/Services.yaml` and can be
injected:

:php:`Webconsulting\X402Paywall\Configuration\ConfigurationProvider`
    Reads the ``x402_paywall`` block of a site into a
    :php:`PaywallConfiguration` value object.

:php:`Webconsulting\X402Paywall\Service\RouteGateResolver`
    Decides whether a request is gated; resolves price, description and page
    UID from the request.

:php:`Webconsulting\X402Paywall\Service\PaymentVerifier`
    Facilitator client: :php:`verify()` returns a :php:`VerifyResponse`,
    :php:`settle()` a :php:`SettlementResponse` (retrying a
    ``settlement_pending`` answer once), :php:`supported()` the answer to
    ``GET /supported``.

:php:`Webconsulting\X402Paywall\Service\FacilitatorAuthentication`
    Request headers for the facilitator; signs the CDP bearer token for
    ``facilitator_auth: cdp``.

:php:`Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory`
    Builds 402 responses (JSON or the HTML paywall page) with the
    ``PAYMENT-REQUIRED`` header, and the 402 for a failed settlement with the
    ``PAYMENT-RESPONSE`` header.

:php:`Webconsulting\X402Paywall\Service\PaymentLogger`
    Writes :sql:`tx_x402_payment_log` (status ``settled``, ``pending`` or
    ``failed``) and provides the dashboard queries; :php:`ReportingPeriod`
    enumerates the periods.

:php:`Webconsulting\X402Paywall\Service\GatedPageFinder`
    Lists the pages with the paywall toggle (simulator, ``x402_gated_pages``).

The models in :php:`Webconsulting\X402Paywall\Domain\Model` mirror the
specification: :php:`PaymentRequired`, :php:`ResourceInfo`,
:php:`PaymentRequirement`, :php:`PaymentPayload`, :php:`VerifyResponse` and
:php:`SettlementResponse`, each with :php:`fromArray()` / :php:`toArray()`
and header encoding where the document travels in a header. Everything that
differs in x402 v1 (headers, alias networks, ``maxAmountRequired`` body) is
isolated in :php:`Webconsulting\X402Paywall\Legacy\X402V1`.

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

Middleware and trust model
==========================

:php:`Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware` runs in the
frontend stack after ``typo3/cms-frontend/prepare-tsfe-rendering`` and
``shortcut-and-mountpoint-redirect`` (the resolved page record is available)
and before ``csp-headers``.

TYPO3 never trusts the client: every payload is compared with the requirement
TYPO3 offered (scheme, network, amount, asset, ``payTo``, ``maxTimeoutSeconds``
and each ``extra`` entry; an ``assetTransferMethod`` other than ``eip3009`` or
a ``paymentFlow`` other than ``authorization`` is refused) and then verified by
the facilitator before the request reaches the page. The payload is forwarded
exactly as received, so empty JSON objects stay objects. The content is
produced first and settled afterwards; a non-2xx response is never charged.
A failed settlement answers 402 with ``PAYMENT-RESPONSE`` and is logged as
``failed``; ``settlement_pending`` is retried once and, like a facilitator that
did not answer after the request was sent, logged as ``pending``. Facilitator
calls use :php:`TYPO3\CMS\Core\Http\RequestFactory` with timeouts (connect
10 s, verify 30 s, settle 60 s). ``x402_probe`` only accepts public HTTP(S)
URLs; the simulator additionally accepts the host of the selected site. The
payment log stores the client IP only as an HMAC (SHA3-256, keyed with the
TYPO3 encryption key); the backend module requires ``admin`` access.

..  _developer-quality-gates:

Quality gates
=============

..  code-block:: bash
    :caption: Local checks (PHP 8.4+, sqlite for functional tests)

    composer install
    composer validate --strict && composer audit
    composer ci                                   # lint, cgl, phpstan (level 8), unit, functional
    Build/Scripts/runTests.sh -s functional -d mariadb

Unit tests live in :file:`tests/Unit`; the functional tests in
:file:`tests/Functional` run the real frontend middleware stack against gated
pages, render both backend submodules and run the payment log queries and MCP
tools against a database. GitHub Actions executes the suites on PHP 8.4 and
8.5 with sqlite and MariaDB 11.4.
