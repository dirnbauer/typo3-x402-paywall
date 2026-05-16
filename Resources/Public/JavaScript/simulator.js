/**
 * x402 Payment Flow Simulator
 * GSAP-animated visualization of the x402 HTTP payment protocol.
 */

const gsap = window.gsap;

// ── DOM refs ────────────────────────────────────────────────────────────────

const scenarioSelect  = document.getElementById('sim-scenario');
const urlInput        = document.getElementById('sim-url');
const descEl          = document.getElementById('sim-desc');
const runBtn          = document.getElementById('sim-run');
const logBody         = document.getElementById('sim-log-body');
const panelRequest    = document.getElementById('panel-request');
const panelResponse   = document.getElementById('panel-response');
const panelReqWrap    = document.getElementById('panel-requirement-wrap');
const panelReq        = document.getElementById('panel-requirement');
const statusBadge     = document.getElementById('res-status-badge');

// Flow nodes / arrows
const nodeClient      = document.getElementById('node-client');
const nodeTypo3       = document.getElementById('node-typo3');
const nodeFacilitator = document.getElementById('node-facilitator');
const arrowCT         = document.getElementById('arrow-c-t');
const arrowTC         = document.getElementById('arrow-t-c');
const labelCT         = document.getElementById('label-c-t');
const labelTC         = document.getElementById('label-t-c');
const badgeCT         = document.getElementById('badge-c-t');
const arrowTF         = document.getElementById('arrow-t-f');
const arrowFT         = document.getElementById('arrow-f-t');
const labelTF         = document.getElementById('label-t-f');
const labelFT         = document.getElementById('label-f-t');
const badgeTF         = document.getElementById('badge-t-f');

const runUrl = document.documentElement.dataset.simRunUrl;

// ── Scenario switching ───────────────────────────────────────────────────────

scenarioSelect.addEventListener('change', () => {
    const opt = scenarioSelect.selectedOptions[0];
    urlInput.value   = opt.dataset.url || '';
    descEl.textContent = opt.dataset.description || '';
    resetDiagram();
});

// ── Helpers ──────────────────────────────────────────────────────────────────

function log(type, message) {
    const ts = new Date().toLocaleTimeString('en', { hour12: false });
    const line = document.createElement('div');
    line.className = `log-line t-${type}`;
    line.textContent = `[${ts}] ${message}`;
    logBody.appendChild(line);
    gsap.fromTo(line, { opacity: 0, x: -10 }, { opacity: 1, x: 0, duration: 0.3 });
    logBody.scrollTop = logBody.scrollHeight;
}

function syntaxJson(obj) {
    return JSON.stringify(obj, null, 2)
        .replace(/("[\w-]+")\s*:/g, '<span class="key">$1</span>:')
        .replace(/:\s*(".*?")/g, ': <span class="val-str">$1</span>')
        .replace(/:\s*(\d+\.?\d*)/g, ': <span class="val-num">$1</span>');
}

function setStatusBadge(code) {
    const cls = code >= 200 && code < 300 ? 's-200' : code === 402 ? 's-402' : 's-0';
    statusBadge.className = `res-status ${cls}`;
    statusBadge.textContent = code || 'ERR';
    gsap.from(statusBadge, { scale: 0, duration: 0.4, ease: 'back.out(2)' });
}

function resetDiagram() {
    gsap.set([arrowCT, arrowTC, labelCT, labelTC, badgeCT,
               arrowTF, arrowFT, labelTF, labelFT, badgeTF], { opacity: 0 });
    gsap.set([nodeClient, nodeTypo3, nodeFacilitator], {
        borderColor: '#0f3460',
        boxShadow: 'none',
    });
    statusBadge.className = 'res-status s-idle';
    statusBadge.textContent = '—';
    panelRequest.innerHTML  = '<pre class="t-idle">Select a scenario and click ▶ Simulate</pre>';
    panelResponse.innerHTML = '<pre class="t-idle">Waiting for simulation...</pre>';
    panelReqWrap.style.display = 'none';
    panelReq.innerHTML = '';
    logBody.innerHTML = '';
}

// ── Animate: Client sends GET → TYPO3 ────────────────────────────────────────

function animateSend(url, hasSignature) {
    const label = hasSignature ? 'GET + PAYMENT-SIGNATURE' : 'GET ' + new URL(url).pathname.slice(0, 30);
    labelCT.textContent = label;

    const tl = gsap.timeline();
    tl.to(nodeClient, { borderColor: '#e94560', boxShadow: '0 0 20px rgba(233,69,96,0.5)', duration: 0.3 })
      .to(labelCT, { opacity: 1, duration: 0.2 }, '-=0.1')
      .set(arrowCT, { left: '0%', opacity: 1 })
      .to(arrowCT, { left: '90%', duration: 0.6, ease: 'power2.inOut' })
      .to(nodeTypo3, { borderColor: '#e94560', boxShadow: '0 0 20px rgba(233,69,96,0.5)', duration: 0.2 }, '-=0.1')
      .to(arrowCT, { opacity: 0, duration: 0.1 });
    return tl;
}

// ── Animate: TYPO3 returns 402 → Client ──────────────────────────────────────

function animate402() {
    labelTC.textContent = '← 402 Payment Required';
    const tl = gsap.timeline();
    tl.to(nodeTypo3, { borderColor: '#c53030', boxShadow: '0 0 24px rgba(197,48,48,0.6)', duration: 0.3 })
      .to(labelTC, { opacity: 1, duration: 0.2 })
      .set(arrowTC, { left: '90%', opacity: 1, textContent: '💳' })
      .to(arrowTC, { left: '0%', duration: 0.6, ease: 'power2.inOut' })
      .to(nodeClient, { borderColor: '#c53030', boxShadow: '0 0 20px rgba(197,48,48,0.4)', duration: 0.2 }, '-=0.1')
      .to(arrowTC, { opacity: 0, duration: 0.1 })
      .to(badgeCT, { opacity: 1, y: -4, duration: 0.3, ease: 'back.out' }, '-=0.1');

    badgeCT.className = 'status-badge s-402';
    badgeCT.textContent = '402';
    return tl;
}

// ── Animate: TYPO3 → Facilitator (verify) ────────────────────────────────────

function animateVerify() {
    labelTF.textContent = '→ Verify signature';
    const tl = gsap.timeline();
    tl.to(nodeTypo3, { borderColor: '#f6e05e', boxShadow: '0 0 20px rgba(246,224,94,0.4)', duration: 0.2 })
      .to(labelTF, { opacity: 1, duration: 0.2 })
      .set(arrowTF, { left: '0%', opacity: 1 })
      .to(arrowTF, { left: '90%', duration: 0.5, ease: 'power2.inOut' })
      .to(nodeFacilitator, { borderColor: '#805ad5', boxShadow: '0 0 20px rgba(128,90,213,0.5)', duration: 0.2 }, '-=0.1')
      .to(arrowTF, { opacity: 0, duration: 0.1 });
    return tl;
}

// ── Animate: Facilitator rejects ─────────────────────────────────────────────

function animateReject() {
    labelFT.textContent = '✗ Rejected';
    badgeTF.className = 'status-badge s-402';
    badgeTF.textContent = 'Invalid';
    const tl = gsap.timeline();
    tl.to(labelFT, { opacity: 1, duration: 0.2 })
      .to(nodeFacilitator, { borderColor: '#c53030', boxShadow: '0 0 20px rgba(197,48,48,0.5)', duration: 0.2 })
      .set(arrowFT, { left: '90%', opacity: 1, textContent: '❌' })
      .to(arrowFT, { left: '0%', duration: 0.5, ease: 'power2.inOut' })
      .to(arrowFT, { opacity: 0, duration: 0.1 })
      .to(badgeTF, { opacity: 1, y: -4, duration: 0.3, ease: 'back.out' }, '-=0.1');
    return tl;
}

// ── Animate: Facilitator accepts ─────────────────────────────────────────────

function animateAccept() {
    labelFT.textContent = '✓ Verified';
    badgeTF.className = 'status-badge s-200';
    badgeTF.textContent = 'Valid';
    const tl = gsap.timeline();
    tl.to(nodeFacilitator, { borderColor: '#00b4d8', boxShadow: '0 0 20px rgba(0,180,216,0.5)', duration: 0.2 })
      .to(labelFT, { opacity: 1, duration: 0.2 })
      .set(arrowFT, { left: '90%', opacity: 1, textContent: '✅' })
      .to(arrowFT, { left: '0%', duration: 0.5, ease: 'power2.inOut' })
      .to(arrowFT, { opacity: 0, duration: 0.1 })
      .to(badgeTF, { opacity: 1, y: -4, duration: 0.3, ease: 'back.out' }, '-=0.1');
    return tl;
}

// ── Animate: TYPO3 returns 200 → Client ──────────────────────────────────────

function animate200() {
    labelTC.textContent = '← 200 OK — content delivered';
    badgeCT.className = 'status-badge s-200';
    badgeCT.textContent = '200';
    const tl = gsap.timeline();
    tl.to(nodeTypo3, { borderColor: '#00b4d8', boxShadow: '0 0 24px rgba(0,180,216,0.5)', duration: 0.2 })
      .to(labelTC, { opacity: 1, duration: 0.2 })
      .set(arrowTC, { left: '90%', opacity: 1, textContent: '📄' })
      .to(arrowTC, { left: '0%', duration: 0.6, ease: 'power2.inOut' })
      .to(nodeClient, { borderColor: '#276749', boxShadow: '0 0 20px rgba(39,103,73,0.5)', duration: 0.2 }, '-=0.1')
      .to(arrowTC, { opacity: 0, duration: 0.1 })
      .to(badgeCT, { opacity: 1, y: -4, duration: 0.3, ease: 'back.out' }, '-=0.1');
    return tl;
}

// ── Main simulation ───────────────────────────────────────────────────────────

runBtn.addEventListener('click', async () => {
    const url = urlInput.value.trim();
    if (!url) { alert('Please enter a URL'); return; }

    const opt       = scenarioSelect.selectedOptions[0];
    const sigMode   = opt.dataset.signature || '';

    resetDiagram();
    runBtn.disabled = true;
    runBtn.textContent = '⏳ Running...';

    logBody.innerHTML = '';
    log('send', `→ ${url}`);
    if (sigMode === 'mock') log('info', '  Including mock PAYMENT-SIGNATURE header');

    panelRequest.innerHTML = `<pre>${'GET ' + url}\nUser-Agent: x402-simulator/TYPO3-backend${sigMode === 'mock' ? '\nPAYMENT-SIGNATURE: [mock base64]' : ''}</pre>`;

    // Step 1: send animation
    const masterTl = gsap.timeline({
        onComplete: () => {
            runBtn.disabled = false;
            runBtn.textContent = '▶ Simulate';
        }
    });
    masterTl.add(animateSend(url, sigMode === 'mock'));
    masterTl.add(() => log('info', '  Request arrived at TYPO3 middleware'));

    // Step 2: call backend
    let result;
    try {
        const resp = await fetch(runUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ url, signature: sigMode }),
        });
        result = await resp.json();
    } catch (e) {
        masterTl.add(() => log('error', '✗ Network error: ' + e.message));
        masterTl.add(animate402());
        masterTl.play();
        return;
    }

    const status = result.status || 0;
    const hasFacilitator = result.steps?.some(s => s.type === 'facilitator');

    // Step 3: animate based on result
    if (status === 402) {
        if (hasFacilitator) {
            masterTl.add(animateVerify());
            masterTl.add(() => log('facilitator', '→ Forwarded to x402.org/facilitator'));
            masterTl.add(animateReject(), '+=0.5');
            masterTl.add(() => log('reject', '  Facilitator rejected: ' + (result.body ? JSON.parse(result.body)?.error || 'invalid' : 'invalid')));
        }
        masterTl.add(animate402());
        masterTl.add(() => {
            log('receive', `← 402 Payment Required (${result.elapsed}ms)`);
            setStatusBadge(402);
        });
    } else if (status >= 200 && status < 300) {
        if (hasFacilitator) {
            masterTl.add(animateVerify());
            masterTl.add(() => log('facilitator', '→ Forwarded to x402.org/facilitator'));
            masterTl.add(animateAccept(), '+=0.5');
            masterTl.add(() => log('receive', '  Facilitator accepted payment'));
        }
        masterTl.add(animate200());
        masterTl.add(() => {
            log('receive', `← ${status} OK (${result.elapsed}ms)`);
            setStatusBadge(status);
        });
    } else {
        masterTl.add(() => {
            log('error', `✗ Error (${result.elapsed ?? 0}ms): ` + (result.error || `HTTP ${status}`));
            setStatusBadge(status);
        });
    }

    // Step 4: populate panels
    masterTl.add(() => {
        // Response panel
        const responseLines = [];
        if (result.headers) {
            responseLines.push(`HTTP/1.1 ${status}`);
            for (const [k, v] of Object.entries(result.headers || {})) {
                responseLines.push(`${k}: ${v}`);
            }
        }
        if (result.error) {
            panelResponse.innerHTML = `<pre class="t-error">Error: ${result.error}</pre>`;
        } else {
            panelResponse.innerHTML = `<pre>${responseLines.join('\n')}</pre>`;
        }

        // Decoded requirement
        if (result.decodedRequirement) {
            panelReqWrap.style.display = 'block';
            panelReq.innerHTML = `<pre>${syntaxJson(result.decodedRequirement)}</pre>`;
            log('info', '📋 Payment requirement decoded (see panel below)');
        }

        log('info', '─── Done ───────────────────────');
    });

    masterTl.play();
});

// ── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const opt = scenarioSelect.selectedOptions[0];
    if (opt) descEl.textContent = opt.dataset.description || '';
});
