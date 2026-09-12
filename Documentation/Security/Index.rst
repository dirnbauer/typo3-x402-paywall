..  include:: ../Includes.rst.txt

..  _security:

========
Security
========

..  _security-trust:

Trust model
===========

TYPO3 never trusts the client: every ``PAYMENT-SIGNATURE`` is compared with
the requirement TYPO3 offered (scheme, network, amount, asset, ``payTo``) and
then verified by the facilitator before the request reaches the page. The
content is produced first and settled afterwards; a non-2xx response is never
charged, a failed settlement answers 402 and is logged with status ``failed``.

..  _security-outbound-requests:

Outbound requests
=================

Facilitator calls use TYPO3's :php:`TYPO3\CMS\Core\Http\RequestFactory` with
timeouts (verify 30 s, settle 60 s, supported 10 s). The backend simulator
and the ``x402_probe`` MCP tool only accept public HTTP(S) URLs; localhost,
private and reserved IP ranges are rejected before any request is sent.

..  _security-payment-logs:

Payment log
===========

:sql:`tx_x402_payment_log` stores amount, network, transaction hash, payer
address, the facilitator response and a truncated user agent. The client IP
is stored only as an HMAC (SHA3-256, keyed with the TYPO3 encryption key).

..  _security-backend-access:

Backend access
==============

The module is registered with ``access: admin``. Wallet address, facilitator
URL and prices are operational payment configuration; keep site configuration
files out of public web roots and version control secrets accordingly.

..  _security-legacy:

Legacy mode
===========

``legacy_v1`` enables the older ``X-PAYMENT`` transport. It goes through the
same verification and settlement path, but v1 network names are less strict
than CAIP-2 identifiers. Keep it disabled unless you must serve v1 clients.
