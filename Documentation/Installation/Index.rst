..  include:: ../Includes.rst.txt

..  _installation:

============
Installation
============

Install the extension with Composer:

..  code-block:: bash
    :caption: Composer installation

    composer require webconsulting/typo3-x402-paywall

TYPO3 loads the extension metadata from :file:`composer.json`.

..  _installation-setup:

Basic setup
===========

1. Configure the :ref:`site settings <configuration-site-settings>`.
2. Enable the x402 paywall tab on the pages that should require payment.
3. Add the :guilabel:`x402 paywall overlay` plugin to traditional frontend
   pages that need a wallet payment prompt.
4. Configure route patterns for headless or API responses.
5. Open the backend module under :guilabel:`Web > x402 Paywall` to inspect
   payment statistics.

..  tip::

    Start on the ``base-sepolia`` test network before switching to production
    networks.
