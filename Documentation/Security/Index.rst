..  include:: ../Includes.rst.txt

..  _security:

========
Security
========

..  _security-outbound-requests:

Outbound requests
=================

Payment verification and settlement use TYPO3 Core's
:php:`TYPO3\CMS\Core\Http\RequestFactory`.

The backend simulator and MCP probe tool only allow public HTTP(S) URLs. They
reject localhost, private IP ranges, and reserved IP ranges before making the
server-side request.

..  _security-payment-logs:

Payment logs
============

Settled and pending payments are stored in ``tx_x402_payment_log``. The request
IP is stored as an HMAC hash through TYPO3 Core's
:php:`TYPO3\CMS\Core\Crypto\HashService`.

..  _security-backend-access:

Backend access
==============

The backend module is registered with admin-only access. Treat facilitator
URLs and wallet settings as operational payment configuration and restrict
access to trusted administrators.
