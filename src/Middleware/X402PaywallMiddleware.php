<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Event\PaymentReceivedEvent;
use Webconsulting\X402Paywall\Event\PaymentRequiredEvent;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Service\RequestAttributeResolver;
use Webconsulting\X402Paywall\Service\RouteGateResolver;
use Webconsulting\X402Paywall\Utility\Json;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

/**
 * PSR-15 middleware implementing the x402 payment protocol.
 *
 * Flow:
 * 1. Check if the route is gated (via config or page field)
 * 2. Look for PAYMENT-SIGNATURE header
 * 3. If missing → return 402 with PAYMENT-REQUIRED header
 * 4. If present → verify with facilitator → pass through or reject
 */
final class X402PaywallMiddleware implements MiddlewareInterface
{
    private const HEADER_PAYMENT_SIGNATURE = 'PAYMENT-SIGNATURE';
    private const HEADER_PAYMENT_REQUIRED = 'PAYMENT-REQUIRED';
    private const HEADER_PAYMENT_RESPONSE = 'PAYMENT-RESPONSE';

    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly RouteGateResolver $gateResolver,
        private readonly PaymentVerifier $verifier,
        private readonly PaymentLogger $paymentLogger,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly RequestAttributeResolver $requestAttributeResolver,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly EventDispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->configProvider->getFromRequest($request);

        // Not enabled or not configured → pass through
        if (!$config->isValid()) {
            return $handler->handle($request);
        }

        // Not a gated route → pass through
        if (!$this->gateResolver->isGated($request, $config)) {
            return $handler->handle($request);
        }

        $price = $this->gateResolver->getPrice($request, $config);
        $requestUri = (string)$request->getUri();
        $description = $this->gateResolver->getContentDescription($request);

        $requirement = PaymentRequirement::fromConfig($config, $requestUri, $price, $description);
        $requirementBase64 = $requirement->toHeaderValue();

        // Check for payment signature
        $paymentSignature = $request->getHeaderLine(self::HEADER_PAYMENT_SIGNATURE);

        if ($paymentSignature === '') {
            // No payment → return 402
            $this->eventDispatcher->dispatch(new PaymentRequiredEvent(
                requestUri: $requestUri,
                price: $price,
                currency: $config->currency,
            ));

            $this->logger->debug('x402: Payment required for {uri}', ['uri' => $requestUri]);

            return $this->create402Response($requirement, $requirementBase64, $price, $config);
        }

        // Payment signature present → verify
        $verification = $this->verifier->verify($paymentSignature, $requirementBase64, $config);

        if (!$verification['valid']) {
            $this->logger->warning('x402: Payment verification failed for {uri}', [
                'uri' => $requestUri,
                'error' => $verification['error'],
            ]);

            return $this->create402Response($requirement, $requirementBase64, $price, $config, $verification['error']);
        }

        // Payment valid → settle and pass through
        $settlement = $this->verifier->settle($paymentSignature, $requirementBase64, $config);

        $pageUid = $this->requestAttributeResolver->getPageUid($request);
        $contentInfo = $this->contentTypeResolver->resolve($request, $pageUid);

        $this->paymentLogger->logPayment(
            request: $request,
            pageUid: $pageUid,
            amount: $price,
            currency: $config->currency,
            network: $config->network,
            txHash: $settlement['txHash'] ?? null,
            status: $settlement['settled'] ? 'settled' : 'pending',
            settlementDetails: $settlement,
            contentType: $contentInfo['type'],
            contentUid: $contentInfo['uid'],
        );

        $this->eventDispatcher->dispatch(new PaymentReceivedEvent(
            requestUri: $requestUri,
            price: $price,
            currency: $config->currency,
            txHash: $settlement['txHash'] ?? null,
            network: $config->network,
        ));

        $this->logger->info('x402: Payment received for {uri}', [
            'uri' => $requestUri,
            'price' => $price,
            'txHash' => $settlement['txHash'] ?? 'pending',
        ]);

        // Add payment response header to the normal response
        $response = $handler->handle($request);

        if ($settlement['settled'] && $settlement['txHash'] !== '') {
            $paymentResponse = base64_encode(Json::encode([
                'scheme' => 'exact',
                'network' => $config->getCaip2NetworkId(),
                'txHash' => $settlement['txHash'],
            ]));
            $response = $response->withHeader(self::HEADER_PAYMENT_RESPONSE, $paymentResponse);
        }

        return $response;
    }

    private function create402Response(
        PaymentRequirement $requirement,
        string $requirementBase64,
        string $price,
        PaywallConfiguration $config,
        ?string $error = null,
    ): ResponseInterface {
        $body = [
            'status' => 402,
            'message' => 'Payment Required',
            'x402' => [
                'version' => '2',
                'requirements' => [$requirement->toArray()],
            ],
            'human_readable' => [
                'price' => $price . ' ' . $config->currency,
                'description' => $requirement->description,
                'network' => $config->network,
            ],
        ];

        if ($error !== null) {
            $body['error'] = $error;
        }

        $response = $this->responseFactory->createResponse(402, 'Payment Required');
        $response = $response->withHeader(self::HEADER_PAYMENT_REQUIRED, $requirementBase64);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withBody(
            $this->streamFactory->createStream(Json::encode($body, JSON_PRETTY_PRINT))
        );

        return $response;
    }
}
