..  include:: ../Includes.rst.txt

..  _developer:

=========
Developer
=========

..  _developer-events:

Events
======

The middleware dispatches two PSR-14 events:

..  php:class:: Webconsulting\X402Paywall\Event\PaymentRequiredEvent

    Dispatched before TYPO3 returns a 402 response.

..  php:class:: Webconsulting\X402Paywall\Event\PaymentReceivedEvent

    Dispatched after a payment was verified and settlement was attempted.

..  _developer-mcp-tools:

MCP tools
=========

The extension registers tools with the ``mcp.tool`` service tag:

* ``x402_probe``
* ``x402_gated_pages``
* ``x402_stats``
* ``x402_transactions``
* ``x402_decode_header``

These tools are intended for TYPO3 MCP server integrations and agent
workflows.

..  _developer-react:

React and Next.js source package
================================

The ``nextjs-components/`` directory contains an optional React/Next.js
companion package:

..  code-block:: bash
    :caption: React package installation after publishing

    npm install @webconsulting/typo3-x402-react

The package contains a hook, overlay component, paid-content wrapper, client,
and Next.js middleware helper.

..  _developer-quality-gates:

Quality gates
=============

Run the local release checks from the project root:

..  code-block:: bash
    :caption: Local quality gates

    composer validate --strict
    Build/Scripts/runTests.sh -s ci
