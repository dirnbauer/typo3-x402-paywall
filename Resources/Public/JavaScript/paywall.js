/**
 * x402 Paywall — Frontend Payment Handler
 *
 * Handles the client-side payment flow:
 * 1. User clicks "Pay"
 * 2. Connect wallet (EIP-1193 provider / window.ethereum)
 * 3. Sign EIP-712 payment authorization
 * 4. Send signed payload to TYPO3 verify endpoint
 * 5. On success: remove paywall overlay, show content
 *
 * Works with MetaMask, Coinbase Wallet, Rabby, and any EIP-1193 wallet.
 */

/* global ethereum */

/**
 * Initiate payment for a gated page.
 * @param {number} pageUid - The TYPO3 page UID
 */
async function x402Pay(pageUid) {
    const container = document.getElementById('x402-paywall-' + pageUid);
    if (!container) return;

    const btn = document.getElementById('x402-pay-btn-' + pageUid);
    const errorEl = document.getElementById('x402-error-' + pageUid);

    // Parse payment requirement from data attribute
    const requirementJson = container.dataset.x402Requirement;
    const requirementBase64 = container.dataset.x402RequirementBase64;
    const verifyEndpoint = container.dataset.x402VerifyEndpoint || '/x402/verify';

    if (!requirementJson) {
        showError(errorEl, 'Payment configuration missing.');
        return;
    }

    let requirement;
    try {
        requirement = JSON.parse(requirementJson);
    } catch {
        showError(errorEl, 'Invalid payment configuration.');
        return;
    }

    // Set loading state
    setButtonLoading(btn, true);
    hideError(errorEl);

    try {
        // Step 1: Check for wallet
        if (typeof window.ethereum === 'undefined') {
            showError(errorEl,
                'No wallet detected. Please install MetaMask, Coinbase Wallet, or another EIP-1193 compatible wallet.'
            );
            setButtonLoading(btn, false);
            return;
        }

        // Step 2: Request account access
        const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
        if (!accounts || accounts.length === 0) {
            showError(errorEl, 'No account selected. Please connect your wallet.');
            setButtonLoading(btn, false);
            return;
        }

        const payerAddress = accounts[0];

        // Step 3: Sign the payment authorization (EIP-712)
        const signature = await signPayment(payerAddress, requirement);
        if (!signature) {
            showError(errorEl, 'Payment signature was cancelled.');
            setButtonLoading(btn, false);
            return;
        }

        // Step 4: Build payment payload
        const paymentPayload = btoa(JSON.stringify({
            signature: signature,
            from: payerAddress,
            scheme: requirement.scheme,
            network: requirement.network,
            amount: requirement.maxAmountRequired,
            payTo: requirement.payTo,
            asset: requirement.asset,
            resource: requirement.resource,
        }));

        // Step 5: Verify via TYPO3 backend
        const verifyResponse = await fetch(verifyEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paymentSignature: paymentPayload,
                paymentRequirement: requirementBase64,
            }),
        });

        const verifyResult = await verifyResponse.json();

        if (verifyResult.valid) {
            // Success — remove paywall
            container.classList.add('x402-paywall--paid');

            // Reload page content without paywall
            window.location.reload();
        } else {
            showError(errorEl, verifyResult.error || 'Payment verification failed. Please try again.');
            setButtonLoading(btn, false);
        }
    } catch (err) {
        console.error('[x402] Payment error:', err);
        showError(errorEl, err.message || 'An unexpected error occurred.');
        setButtonLoading(btn, false);
    }
}

/**
 * Sign an EIP-712 typed data payment authorization.
 * @param {string} from - Payer wallet address
 * @param {object} requirement - x402 payment requirement
 * @returns {Promise<string|null>} - Signature hex string or null if cancelled
 */
async function signPayment(from, requirement) {
    const domain = {
        name: 'x402',
        version: '2',
    };

    const types = {
        EIP712Domain: [
            { name: 'name', type: 'string' },
            { name: 'version', type: 'string' },
        ],
        PaymentAuthorization: [
            { name: 'scheme', type: 'string' },
            { name: 'network', type: 'string' },
            { name: 'amount', type: 'string' },
            { name: 'payTo', type: 'address' },
            { name: 'resource', type: 'string' },
        ],
    };

    const message = {
        scheme: requirement.scheme,
        network: requirement.network,
        amount: requirement.maxAmountRequired,
        payTo: requirement.payTo,
        resource: requirement.resource,
    };

    const typedData = JSON.stringify({
        types: types,
        domain: domain,
        primaryType: 'PaymentAuthorization',
        message: message,
    });

    try {
        const signature = await window.ethereum.request({
            method: 'eth_signTypedData_v4',
            params: [from, typedData],
        });
        return signature;
    } catch (err) {
        if (err.code === 4001) {
            // User rejected the signature
            return null;
        }
        throw err;
    }
}

/**
 * Show an error message.
 * @param {HTMLElement|null} el
 * @param {string} message
 */
function showError(el, message) {
    if (!el) return;
    el.textContent = message;
    el.style.display = 'block';
}

/**
 * Hide the error message.
 * @param {HTMLElement|null} el
 */
function hideError(el) {
    if (!el) return;
    el.style.display = 'none';
    el.textContent = '';
}

/**
 * Set button loading state.
 * @param {HTMLElement|null} btn
 * @param {boolean} loading
 */
function setButtonLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.originalText = btn.textContent;
        btn.innerHTML = '<span class="x402-paywall__spinner"></span>Processing...';
    } else {
        btn.textContent = btn.dataset.originalText || 'Pay';
    }
}
