..  include:: ../Includes.rst.txt

..  _introduction:

============
Introduction
============

The x402 protocol uses the HTTP 402 status code for machine-readable payment
requirements. A client requests protected content, TYPO3 responds with payment
terms, the client signs a payment payload, and TYPO3 verifies and settles the
payment through the configured facilitator.

..  _features:

Features
========

* PSR-15 middleware for page and route gating.
* Page fields for paywall enablement, price, and prompt text.
* Frontend overlay plugin for wallet-based payment flows.
* Backend dashboard for revenue and transaction analytics.
* PSR-14 events for integrations.
* MCP tools for agent-oriented discovery, probing, and reporting.
* React/Next.js companion source package for headless projects.

..  _requirements:

Requirements
============

* TYPO3 14.3 or later.
* PHP 8.2 or later.
* A wallet address for the selected network.
* Outbound HTTPS access to the x402 facilitator.
