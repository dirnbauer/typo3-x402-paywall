/**
 * x402 payment flow simulator for the TYPO3 backend module.
 */

const root = document.getElementById('x402-simulator');

if (root) {
    const scenarioSelect = document.getElementById('sim-scenario');
    const urlInput = document.getElementById('sim-url');
    const description = document.getElementById('sim-desc');
    const runButton = document.getElementById('sim-run');
    const requestPanel = document.getElementById('panel-request');
    const responsePanel = document.getElementById('panel-response');
    const requirementWrap = document.getElementById('panel-requirement-wrap');
    const requirementPanel = document.getElementById('panel-requirement');

    scenarioSelect.addEventListener('change', () => {
        const option = scenarioSelect.selectedOptions[0];
        urlInput.value = option.dataset.url || '';
        description.textContent = option.dataset.description || '';
        resetPanels();
    });

    runButton.addEventListener('click', async () => {
        const option = scenarioSelect.selectedOptions[0];
        const signatureMode = option.dataset.signature || '';
        const url = urlInput.value.trim();

        resetPanels();
        runButton.disabled = true;
        runButton.textContent = root.dataset.labelRunning || '';
        requestPanel.textContent = buildRequestPreview(url, signatureMode);

        try {
            const response = await fetch(root.dataset.runUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ url, signature: signatureMode }),
            });
            const result = await response.json();
            renderResult(result);
        } catch (error) {
            responsePanel.textContent = `${root.dataset.labelError}: ${error.message}`;
        } finally {
            runButton.disabled = false;
            runButton.textContent = root.dataset.labelRun || '';
        }
    });

    function resetPanels() {
        requestPanel.textContent = root.dataset.labelWaitingRequest || '';
        responsePanel.textContent = root.dataset.labelWaitingResponse || '';
        requirementWrap.hidden = true;
        requirementPanel.textContent = '';
    }

    function renderResult(result) {
        if (result.error) {
            responsePanel.textContent = `${root.dataset.labelError}: ${result.error}`;
            return;
        }

        const lines = [`HTTP ${result.status || 0}`];
        for (const [name, value] of Object.entries(result.headers || {})) {
            lines.push(`${name}: ${value}`);
        }
        if (typeof result.body === 'string' && result.body !== '') {
            lines.push('', result.body);
        }
        responsePanel.textContent = lines.join('\n');

        if (result.decodedRequirement) {
            requirementWrap.hidden = false;
            requirementPanel.textContent = JSON.stringify(result.decodedRequirement, null, 2);
        }
    }

    function buildRequestPreview(url, signatureMode) {
        const lines = [`GET ${url}`, 'User-Agent: x402-simulator/TYPO3-backend'];
        if (signatureMode === 'mock') {
            lines.push('PAYMENT-SIGNATURE: [mock base64]');
        }

        return lines.join('\n');
    }
}
