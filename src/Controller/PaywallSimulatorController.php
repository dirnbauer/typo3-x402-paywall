<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Http\X402Header;
use Webconsulting\X402Paywall\Service\GatedPageFinder;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Utility\HttpUrl;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * "x402 Paywall > Simulator": plays the client side of the x402 exchange against a site of
 * this installation and shows every request and response with the decoded x402 headers.
 *
 * Requests go to the selected site (its own host is allowed even when it resolves to a private
 * address, as in local development) or to public http(s) URLs; nothing is ever paid: the "mock
 * signature" scenario sends a well-formed payment with an invalid signature.
 */
#[AsController]
final readonly class PaywallSimulatorController
{
    public const string ROUTE = 'web_x402_paywall_simulator';

    public const string SCENARIO_UNPAID = 'unpaid';
    public const string SCENARIO_MOCK_SIGNATURE = 'mock_signature';
    public const string SCENARIO_FACILITATOR = 'facilitator';

    private const int PROBE_TIMEOUT = 10;
    private const int BODY_PREVIEW_BYTES = 2000;
    private const string USER_AGENT = 'x402-simulator/TYPO3-backend';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private SiteFinder $siteFinder,
        private ConfigurationProvider $configurationProvider,
        private GatedPageFinder $gatedPageFinder,
        private PaymentVerifier $verifier,
        private RequestFactory $requestFactory,
        private UriBuilder $uriBuilder,
        private PageRenderer $pageRenderer,
        private ModuleLabels $labels,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $title = $this->labels->get('simulator.title');
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($title);
        $view->makeDocHeaderModuleMenu();
        $view->getDocHeaderComponent()->setShortcutContext(self::ROUTE, $title);

        $sites = $this->siteFinder->getAllSites();
        $site = $this->selectedSite($request, $sites);
        $view->assignMultiple([
            'sites' => array_map(static fn(Site $candidate): array => [
                'identifier' => $candidate->getIdentifier(),
                'selected' => $candidate === $site,
            ], array_values($sites)),
            'site' => $site?->getIdentifier() ?? '',
            'scenarios' => $site instanceof Site ? $this->scenarios($site, $request) : [],
            'runUri' => (string)$this->uriBuilder->buildUriFromRoute(self::ROUTE . '.run'),
            'dashboardUri' => (string)$this->uriBuilder->buildUriFromRoute(PaywallDashboardController::ROUTE),
        ]);
        $this->pageRenderer->loadJavaScriptModule('@webconsulting/x402-paywall/simulator.js');
        $this->pageRenderer->addCssFile('EXT:x402_paywall/Resources/Public/Css/backend.css');

        return $view->renderResponse('Simulator/Main');
    }

    /**
     * AJAX: runs one scenario and returns the exchange as JSON.
     */
    public function runAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = Json::decodeObject((string)$request->getBody());
        } catch (\JsonException) {
            return $this->error('simulator.error.invalid_request');
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier(ScalarValue::string($input['site'] ?? null));
        } catch (SiteNotFoundException) {
            return $this->error('simulator.error.unknown_site');
        }
        $config = $this->configurationProvider->getForSite($site);
        $scenario = ScalarValue::string($input['scenario'] ?? null);

        if ($scenario === self::SCENARIO_FACILITATOR) {
            return $this->checkFacilitator($config);
        }

        $url = ScalarValue::string($input['url'] ?? null);
        if ($url === '') {
            return $this->error('simulator.error.missing_url');
        }
        if (!self::isOnSite($url, $this->siteBaseUrl($site, $request)) && !HttpUrl::isAllowedOutboundHttpUrl($url)) {
            return $this->error('simulator.error.disallowed_url');
        }

        $headers = ['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT];
        $steps = [];
        try {
            $response = $this->requestFactory->request($url, 'GET', $this->requestOptions($headers));
            $steps[] = $this->step($url, $headers, $response);
            $offered = self::paymentRequired($response);

            if ($scenario === self::SCENARIO_MOCK_SIGNATURE && $offered instanceof PaymentRequired) {
                $payment = self::mockPayment($offered);
                $headers[X402Header::PAYMENT_SIGNATURE] = base64_encode(Json::encode($payment, JSON_UNESCAPED_SLASHES));
                $response = $this->requestFactory->request($url, 'GET', $this->requestOptions($headers));
                $steps[] = $this->step($url, $headers, $response, $payment);
            }
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'steps' => $steps,
                'summary' => $this->summary('danger', 'simulator.outcome.unreachable', ['error' => $exception->getMessage()]),
            ]);
        }

        return new JsonResponse(['steps' => $steps, 'summary' => $this->outcome($scenario, $response)]);
    }

    /**
     * @param array<string, Site> $sites
     */
    private function selectedSite(ServerRequestInterface $request, array $sites): ?Site
    {
        $identifier = ScalarValue::string($request->getQueryParams()['site'] ?? null);
        if (isset($sites[$identifier])) {
            return $sites[$identifier];
        }

        return array_find($sites, static fn(Site $site): bool => is_array($site->getConfiguration()['x402_paywall'] ?? null))
            ?? (array_values($sites)[0] ?? null);
    }

    /**
     * Scenarios with a real target on the site: its first paywalled page, its first gated route pattern.
     *
     * @return list<array{id: string, label: string, description: string, url: string}>
     */
    private function scenarios(Site $site, ServerRequestInterface $request): array
    {
        $config = $this->configurationProvider->getForSite($site);
        $pageUrl = $this->gatedPageUrl($site, $config);
        $baseUrl = rtrim($this->siteBaseUrl($site, $request), '/');

        $scenarios = [];
        $scenarios[] = $this->scenario(self::SCENARIO_UNPAID, $pageUrl ?? $baseUrl . '/');
        $scenarios[] = $this->scenario(self::SCENARIO_MOCK_SIGNATURE, $pageUrl ?? $baseUrl . '/');
        $pattern = $config->gatedRoutePatterns[0] ?? '';
        if ($pattern !== '') {
            $scenarios[] = $this->scenario('route_pattern', $baseUrl . '/' . ltrim(str_replace('*', 'example', $pattern), '/'));
        }
        $scenarios[] = $this->scenario(self::SCENARIO_FACILITATOR, rtrim($config->facilitatorUrl, '/') . '/supported');

        return $scenarios;
    }

    /**
     * @return array{id: string, label: string, description: string, url: string}
     */
    private function scenario(string $id, string $url): array
    {
        return [
            'id' => $id,
            'label' => $this->labels->get('simulator.scenario.' . $id),
            'description' => $this->labels->get('simulator.scenario.' . $id . '.description'),
            'url' => $url,
        ];
    }

    private function gatedPageUrl(Site $site, PaywallConfiguration $config): ?string
    {
        $candidates = [
            ...$config->gatedPageUids,
            ...array_column($this->gatedPageFinder->findToggledPages(), 'uid'),
        ];
        foreach ($candidates as $pageUid) {
            try {
                if ($this->siteFinder->getSiteByPageId($pageUid)->getIdentifier() === $site->getIdentifier()) {
                    return (string)$site->getRouter()->generateUri($pageUid);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function checkFacilitator(PaywallConfiguration $config): ResponseInterface
    {
        $url = rtrim($config->facilitatorUrl, '/') . '/supported';
        $step = ['request' => ['method' => 'GET', 'url' => $url, 'headers' => $config->usesCdpAuthentication() ? ['Authorization' => 'Bearer …'] : []]];
        try {
            $supported = $this->verifier->supported($config);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'steps' => [$step],
                'summary' => $this->summary('danger', 'simulator.outcome.facilitator_failed', ['error' => $exception->getMessage()]),
            ]);
        }

        $network = $config->getCaip2NetworkId();
        $kinds = is_array($supported['kinds'] ?? null) ? $supported['kinds'] : [];
        $networkSupported = array_any(
            $kinds,
            static fn(mixed $kind): bool => is_array($kind)
                && ScalarValue::int($kind['x402Version'] ?? null) === PaymentRequired::X402_VERSION
                && ScalarValue::string($kind['scheme'] ?? null) === 'exact'
                && ScalarValue::string($kind['network'] ?? null) === $network,
        );
        $step['response'] = ['status' => 200, 'headers' => [], 'body' => Json::encode($supported, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];

        return new JsonResponse([
            'steps' => [$step],
            'summary' => $networkSupported
                ? $this->summary('success', 'simulator.outcome.facilitator_supports', ['network' => $network])
                : $this->summary('warning', 'simulator.outcome.facilitator_lacks', ['network' => $network]),
        ]);
    }

    /**
     * @return array{severity: string, message: string}
     */
    private function outcome(string $scenario, ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $paymentRequired = self::paymentRequired($response);
        if ($status === 402 && $paymentRequired instanceof PaymentRequired) {
            return $paymentRequired->error !== null
                ? $this->summary($scenario === self::SCENARIO_MOCK_SIGNATURE ? 'success' : 'warning', 'simulator.outcome.rejected', ['reason' => $paymentRequired->error])
                : $this->summary('success', 'simulator.outcome.payment_required', ['amount' => $paymentRequired->first()->amount, 'network' => $paymentRequired->first()->network]);
        }
        if ($status === 402 && $response->hasHeader(X402Header::PAYMENT_RESPONSE)) {
            return $this->summary('warning', 'simulator.outcome.settlement_failed', ['reason' => self::settlement($response)->errorReason ?? '']);
        }
        if ($status >= 200 && $status < 300) {
            return $this->summary('warning', 'simulator.outcome.not_gated', ['status' => $status]);
        }

        return $this->summary('danger', 'simulator.outcome.unexpected_status', ['status' => $status]);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payment PaymentPayload sent in PAYMENT-SIGNATURE, if any
     * @return array<string, mixed>
     */
    private function step(string $url, array $headers, ResponseInterface $response, array $payment = []): array
    {
        $decoded = [];
        $paymentRequired = self::paymentRequired($response);
        if ($paymentRequired instanceof PaymentRequired) {
            $decoded[X402Header::PAYMENT_REQUIRED] = $paymentRequired->toArray();
        }
        $settlement = self::settlement($response);
        if ($settlement instanceof SettlementResponse) {
            $decoded[X402Header::PAYMENT_RESPONSE] = $settlement->toArray();
        }
        if ($payment !== [] && isset($headers[X402Header::PAYMENT_SIGNATURE])) {
            $decoded[X402Header::PAYMENT_SIGNATURE] = $payment;
            $headers[X402Header::PAYMENT_SIGNATURE] = substr($headers[X402Header::PAYMENT_SIGNATURE], 0, 24) . '…';
        }

        return [
            'request' => ['method' => 'GET', 'url' => $url, 'headers' => $headers],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => array_map(static fn(array $values): string => implode(', ', $values), $response->getHeaders()),
                'body' => substr((string)$response->getBody(), 0, self::BODY_PREVIEW_BYTES),
            ],
            'decoded' => $decoded === [] ? new \stdClass() : $decoded,
        ];
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @return array{severity: string, message: string}
     */
    private function summary(string $severity, string $labelKey, array $arguments = []): array
    {
        return ['severity' => $severity, 'message' => $this->labels->get($labelKey, $arguments)];
    }

    private function error(string $labelKey): JsonResponse
    {
        return new JsonResponse(['error' => $this->labels->get($labelKey)], 400);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function requestOptions(array $headers): array
    {
        return ['headers' => $headers, 'timeout' => self::PROBE_TIMEOUT, 'allow_redirects' => false, 'http_errors' => false];
    }

    /**
     * Absolute base URL of the site; a relative base ("/") is resolved against the backend host.
     */
    private function siteBaseUrl(Site $site, ServerRequestInterface $request): string
    {
        $base = $site->getBase();
        if ($base->getHost() !== '') {
            return (string)$base;
        }
        $backend = $request->getUri();

        return (string)$backend->withPath('/' . ltrim($base->getPath(), '/'))->withQuery('')->withFragment('');
    }

    /**
     * Same scheme, host and port as the site base.
     */
    private static function isOnSite(string $url, string $siteBaseUrl): bool
    {
        $target = parse_url($url);
        $base = parse_url($siteBaseUrl);
        if (!is_array($target) || !is_array($base)) {
            return false;
        }

        $scheme = strtolower($target['scheme'] ?? '');

        return in_array($scheme, ['http', 'https'], true)
            && $scheme === strtolower($base['scheme'] ?? '')
            && strtolower($target['host'] ?? '') === strtolower($base['host'] ?? '')
            && ($target['port'] ?? null) === ($base['port'] ?? null);
    }

    private static function paymentRequired(ResponseInterface $response): ?PaymentRequired
    {
        $header = $response->getHeaderLine(X402Header::PAYMENT_REQUIRED);
        if ($header === '') {
            return null;
        }
        try {
            return PaymentRequired::fromHeaderValue($header);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function settlement(ResponseInterface $response): ?SettlementResponse
    {
        $header = $response->getHeaderLine(X402Header::PAYMENT_RESPONSE);
        if ($header === '') {
            return null;
        }
        try {
            return SettlementResponse::fromHeaderValue($header);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * x402 v2 PaymentPayload (exact/EVM, EIP-3009) for the first offered requirement with a dummy
     * signature: well-formed, so the facilitator is asked, and certain to be rejected.
     *
     * @return array<string, mixed>
     */
    private static function mockPayment(PaymentRequired $offered): array
    {
        $requirement = $offered->first();
        $now = time();

        return [
            'x402Version' => PaymentRequired::X402_VERSION,
            'resource' => $offered->resource->toArray(),
            'accepted' => $requirement->toArray(),
            'payload' => [
                'signature' => '0x' . str_repeat('ab', 65),
                'authorization' => [
                    'from' => '0x0000000000000000000000000000000000000001',
                    'to' => $requirement->payTo,
                    'value' => $requirement->amount,
                    'validAfter' => (string)($now - 60),
                    'validBefore' => (string)($now + $requirement->maxTimeoutSeconds),
                    'nonce' => '0x' . bin2hex(random_bytes(32)),
                ],
            ],
        ];
    }
}
