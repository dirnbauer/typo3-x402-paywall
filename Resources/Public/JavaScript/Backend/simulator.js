/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

/**
 * "x402 Paywall > Simulator": runs a scenario on the server and shows the exchange.
 * Every value from the response is rendered as text, never as markup.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import labels from '~labels/x402_paywall.mod';

class X402Simulator {
  constructor(form) {
    this.form = form;
    this.scenario = form.querySelector('[name="scenario"]');
    this.url = form.querySelector('[name="url"]');
    this.description = form.querySelector('[data-x402-scenario-description]');
    this.submit = form.querySelector('button[type="submit"]');
    this.output = document.getElementById(form.dataset.x402Output);

    form.querySelector('[name="site"]')?.addEventListener('change', (event) => {
      const option = event.target.selectedOptions[0];
      if (option?.dataset.url) {
        window.location.href = option.dataset.url;
      }
    });
    this.scenario.addEventListener('change', () => this.selectScenario());
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.run();
    });
    this.selectScenario();
  }

  selectScenario() {
    const option = this.scenario.selectedOptions[0];
    this.url.value = option?.dataset.url ?? '';
    // The facilitator check always calls the configured facilitator.
    this.url.readOnly = option?.value === 'facilitator';
    this.description.textContent = option?.dataset.description ?? '';
  }

  async run() {
    this.setBusy(true);
    this.output.replaceChildren();
    try {
      // The controller reads a JSON body; without the header AjaxRequest sends FormData.
      const response = await new AjaxRequest(this.form.action).post({
        site: this.form.elements.site.value,
        scenario: this.scenario.value,
        url: this.url.value.trim(),
      }, { headers: { 'Content-Type': 'application/json' } });
      this.render(await response.resolve());
    } catch (error) {
      let message = error instanceof Error ? error.message : '';
      if (typeof error?.resolve === 'function') {
        const result = await error.resolve().catch(() => ({}));
        message = result.error || labels.get('simulator.error.request_failed');
      }
      this.output.append(this.callout('danger', message || labels.get('simulator.error.request_failed')));
    } finally {
      this.setBusy(false);
    }
  }

  render(result) {
    if (result.summary) {
      this.output.append(this.callout(result.summary.severity, result.summary.message));
    }
    (result.steps || []).forEach((step, index) => this.output.append(this.stepCard(step, index + 1)));
  }

  stepCard(step, number) {
    const card = this.element('div', 'card');
    const header = this.element('div', 'card-header');
    const headerBody = this.element('div', 'card-header-body');
    const title = this.element('h2', 'card-title', labels.get('simulator.step', { number: String(number) }));
    const subtitle = this.element('span', 'card-subtitle');
    subtitle.append(this.element('code', '', `${step.request.method} ${step.request.url}`));
    if (step.response) {
      subtitle.append(' ', this.statusBadge(step.response.status));
    }
    headerBody.append(title, subtitle);
    header.append(headerBody);

    const body = this.element('div', 'card-body');
    const row = this.element('div', 'row g-3');
    row.append(this.panel(labels.get('simulator.request'), this.formatRequest(step.request)));
    if (step.response) {
      row.append(this.panel(labels.get('simulator.response'), this.formatResponse(step.response)));
    }
    body.append(row);

    Object.entries(step.decoded || {}).forEach(([header, document]) => {
      body.append(
        this.element('h3', 'h4 mt-3', labels.get('simulator.decoded', { header })),
        this.element('pre', 'x402-simulator-code', JSON.stringify(document, null, 2)),
      );
    });

    card.append(header, body);
    return card;
  }

  panel(title, text) {
    const column = this.element('div', 'col-lg-6');
    column.append(this.element('h3', 'h4', title), this.element('pre', 'x402-simulator-code', text));
    return column;
  }

  formatRequest(request) {
    const lines = [`${request.method} ${request.url}`];
    Object.entries(request.headers || {}).forEach(([name, value]) => lines.push(`${name}: ${value}`));
    return lines.join('\n');
  }

  formatResponse(response) {
    const lines = [`HTTP ${response.status}`];
    Object.entries(response.headers || {}).forEach(([name, value]) => lines.push(`${name}: ${value}`));
    if (response.body) {
      lines.push('', response.body);
    }
    return lines.join('\n');
  }

  statusBadge(status) {
    const severity = status >= 200 && status < 300 ? 'success' : status === 402 ? 'warning' : 'danger';
    return this.element('span', `badge badge-${severity}`, `HTTP ${status}`);
  }

  callout(severity, message) {
    const callout = this.element('div', `callout callout-${severity}`);
    callout.setAttribute('role', severity === 'danger' ? 'alert' : 'status');
    const content = this.element('div', 'callout-content');
    content.append(this.element('div', 'callout-body', message));
    callout.append(content);
    return callout;
  }

  setBusy(busy) {
    this.submit.disabled = busy;
    this.output.setAttribute('aria-busy', busy ? 'true' : 'false');
    this.submit.querySelector('[data-x402-submit-label]').textContent = labels.get(busy ? 'simulator.running' : 'simulator.run');
  }

  element(tagName, className, text) {
    const element = document.createElement(tagName);
    if (className) {
      element.className = className;
    }
    if (text !== undefined) {
      element.textContent = text;
    }
    return element;
  }
}

document.querySelectorAll('form[data-x402-simulator]').forEach((form) => new X402Simulator(form));
