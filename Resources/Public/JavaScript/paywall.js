/**
 * x402 paywall - browser wallet client (x402 v2, scheme "exact" on EVM networks, EIP-3009).
 *
 * Flow:
 *   1. Read the PaymentRequired document embedded by the 402 page.
 *   2. Connect an EIP-1193 wallet (MetaMask, Coinbase Wallet, Rabby, ...) and switch to the required chain.
 *   3. Sign a TransferWithAuthorization (EIP-712) for the token named in the requirement.
 *   4. Re-request the resource with the base64 PaymentPayload in the PAYMENT-SIGNATURE header.
 *   5. Replace the page with the paid response.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-x402-paywall]');
    if (!root) {
        return;
    }

    const payButton = root.querySelector('[data-x402-pay]');
    const errorBox = root.querySelector('[data-x402-error]');
    const labels = {
        missingConfig: root.dataset.x402LabelMissingConfig || 'Payment configuration is missing.',
        invalidConfig: root.dataset.x402LabelInvalidConfig || 'Payment configuration is invalid.',
        noWallet: root.dataset.x402LabelNoWallet || 'No wallet found.',
        noAccount: root.dataset.x402LabelNoAccount || 'No account selected.',
        wrongNetwork: root.dataset.x402LabelWrongNetwork || 'Please switch your wallet to the required network.',
        signatureCancelled: root.dataset.x402LabelSignatureCancelled || 'Signature cancelled.',
        verificationFailed: root.dataset.x402LabelVerificationFailed || 'Payment was not accepted.',
        unexpected: root.dataset.x402LabelUnexpected || 'Unexpected error.',
        processing: root.dataset.x402LabelProcessing || 'Processing...',
        success: root.dataset.x402LabelSuccess || 'Payment accepted, loading content...',
    };

    if (payButton) {
        payButton.addEventListener('click', pay);
    }

    async function pay() {
        hideError();

        let paymentRequired;
        try {
            paymentRequired = JSON.parse(root.dataset.x402PaymentRequired || '');
        } catch (error) {
            showError(labels.invalidConfig);
            return;
        }

        const requirement = paymentRequired && Array.isArray(paymentRequired.accepts) ? paymentRequired.accepts[0] : null;
        if (!requirement || !requirement.payTo || !requirement.asset || !requirement.amount) {
            showError(labels.missingConfig);
            return;
        }

        if (typeof window.ethereum === 'undefined') {
            showError(labels.noWallet);
            return;
        }

        setLoading(true);

        try {
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            const from = Array.isArray(accounts) && accounts.length > 0 ? accounts[0] : null;
            if (!from) {
                showError(labels.noAccount);
                return;
            }

            const chainId = parseChainId(requirement.network);
            if (chainId === null || !(await ensureChain(chainId))) {
                showError(labels.wrongNetwork);
                return;
            }

            const authorization = buildAuthorization(from, requirement);
            const signature = await signAuthorization(from, chainId, requirement, authorization);
            if (signature === null) {
                showError(labels.signatureCancelled);
                return;
            }

            const paymentPayload = {
                x402Version: 2,
                resource: paymentRequired.resource,
                accepted: requirement,
                payload: { signature: signature, authorization: authorization },
            };

            const resourceUrl = (paymentRequired.resource && paymentRequired.resource.url) || root.dataset.x402Resource || window.location.href;
            const response = await fetch(resourceUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml,*/*;q=0.8',
                    'PAYMENT-SIGNATURE': base64Utf8(JSON.stringify(paymentPayload)),
                },
            });

            if (response.ok) {
                setStatus(labels.success);
                const html = await response.text();
                document.open();
                document.write(html);
                document.close();
                return;
            }

            showError(await readError(response));
        } catch (error) {
            console.error('[x402] payment failed', error);
            showError(labels.unexpected);
        } finally {
            setLoading(false);
        }
    }

    function parseChainId(network) {
        const match = /^eip155:(\d+)$/.exec(network || '');
        return match ? parseInt(match[1], 10) : null;
    }

    async function ensureChain(chainId) {
        const wanted = '0x' + chainId.toString(16);
        const current = await window.ethereum.request({ method: 'eth_chainId' });
        if (typeof current === 'string' && current.toLowerCase() === wanted) {
            return true;
        }
        try {
            await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: wanted }] });
            return true;
        } catch (error) {
            return false;
        }
    }

    function buildAuthorization(from, requirement) {
        const now = Math.floor(Date.now() / 1000);
        const timeout = Number(requirement.maxTimeoutSeconds) > 0 ? Number(requirement.maxTimeoutSeconds) : 300;
        const nonce = new Uint8Array(32);
        window.crypto.getRandomValues(nonce);

        return {
            from: from,
            to: requirement.payTo,
            value: String(requirement.amount),
            validAfter: String(now - 600),
            validBefore: String(now + timeout),
            nonce: '0x' + Array.from(nonce, (byte) => byte.toString(16).padStart(2, '0')).join(''),
        };
    }

    async function signAuthorization(from, chainId, requirement, authorization) {
        const extra = requirement.extra || {};
        const typedData = {
            types: {
                EIP712Domain: [
                    { name: 'name', type: 'string' },
                    { name: 'version', type: 'string' },
                    { name: 'chainId', type: 'uint256' },
                    { name: 'verifyingContract', type: 'address' },
                ],
                TransferWithAuthorization: [
                    { name: 'from', type: 'address' },
                    { name: 'to', type: 'address' },
                    { name: 'value', type: 'uint256' },
                    { name: 'validAfter', type: 'uint256' },
                    { name: 'validBefore', type: 'uint256' },
                    { name: 'nonce', type: 'bytes32' },
                ],
            },
            primaryType: 'TransferWithAuthorization',
            domain: {
                name: extra.name || 'USD Coin',
                version: extra.version || '2',
                chainId: chainId,
                verifyingContract: requirement.asset,
            },
            message: authorization,
        };

        try {
            return await window.ethereum.request({
                method: 'eth_signTypedData_v4',
                params: [from, JSON.stringify(typedData)],
            });
        } catch (error) {
            if (error && error.code === 4001) {
                return null;
            }
            throw error;
        }
    }

    async function readError(response) {
        const header = response.headers.get('PAYMENT-REQUIRED');
        if (header) {
            try {
                const document = JSON.parse(atob(header));
                if (document && document.error) {
                    return labels.verificationFailed + ' (' + document.error + ')';
                }
            } catch (error) {
                // fall through
            }
        }
        return labels.verificationFailed;
    }

    function base64Utf8(text) {
        const bytes = new TextEncoder().encode(text);
        let binary = '';
        bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
        return btoa(binary);
    }

    function showError(message) {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function hideError() {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = '';
        errorBox.hidden = true;
    }

    function setStatus(message) {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = message;
        errorBox.classList.add('x402-paywall__error--info');
        errorBox.hidden = false;
    }

    function setLoading(loading) {
        if (!payButton) {
            return;
        }
        payButton.disabled = loading;
        if (loading) {
            payButton.dataset.originalText = payButton.textContent.trim();
            payButton.textContent = '';
            const spinner = document.createElement('span');
            spinner.className = 'x402-paywall__spinner';
            payButton.appendChild(spinner);
            payButton.appendChild(document.createTextNode(labels.processing));
        } else {
            payButton.textContent = payButton.dataset.originalText || '';
        }
    }
})();
