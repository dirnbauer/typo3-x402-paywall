<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Service\RequestAttributeResolver;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Frontend plugin controller for x402 paywall.
 *
 * Renders a paywall overlay on gated pages and handles
 * payment verification via AJAX.
 */
class PaywallController extends ActionController
{
    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly PaymentVerifier $verifier,
        private readonly PaymentLogger $paymentLogger,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly RequestAttributeResolver $requestAttributeResolver,
    ) {}

    /**
     * Main action: renders the paywall overlay or full content.
     */
    public function showAction(): ResponseInterface
    {
        $config = $this->configProvider->getFromRequest($this->request);

        if (!$config->isValid()) {
            // x402 not configured — render nothing (content shows normally)
            $this->view->assign('paywallActive', false);
            return $this->htmlResponse();
        }

        // Check if current page is gated
        $pageRecord = $this->requestAttributeResolver->getPageRecord($this->request);
        $isGated = (bool)($pageRecord['tx_x402_paywall_enabled'] ?? false);

        if (!$isGated) {
            $this->view->assign('paywallActive', false);
            return $this->htmlResponse();
        }

        $price = $this->nonEmptyString($pageRecord['tx_x402_paywall_price'] ?? null, $config->defaultPrice);
        $description = $this->nonEmptyString(
            $pageRecord['tx_x402_paywall_description'] ?? null,
            $this->nonEmptyString($pageRecord['title'] ?? null, ''),
        );

        // Build payment requirement for the frontend JavaScript
        $paymentRequirement = PaymentRequirement::fromConfig(
            $config,
            (string)$this->request->getUri(),
            $price,
            $description,
        );

        $this->view->assignMultiple([
            'paywallActive' => true,
            'price' => $price,
            'currency' => $config->currency,
            'description' => $description,
            'network' => $config->network,
            'networkLabel' => $this->getNetworkLabel($config->network),
            'freePreviewParagraphs' => $config->freePreviewParagraphs,
            'paymentRequirement' => Json::encode($paymentRequirement->toArray()),
            'paymentRequirementBase64' => $paymentRequirement->toHeaderValue(),
            'verifyEndpoint' => '/x402/verify',
            'pageUid' => $pageRecord['uid'] ?? 0,
        ]);

        return $this->htmlResponse();
    }

    /**
     * AJAX action: verify a payment signature from the frontend.
     */
    public function verifyAction(): ResponseInterface
    {
        $config = $this->configProvider->getFromRequest($this->request);
        try {
            $body = Json::decodeObject((string)$this->request->getBody());
        } catch (\JsonException) {
            return new JsonResponse(['valid' => false, 'error' => 'Invalid payment data'], 400);
        }

        $paymentSignature = $this->nonEmptyString($body['paymentSignature'] ?? null);
        $paymentRequirement = $this->nonEmptyString($body['paymentRequirement'] ?? null);

        if ($paymentSignature === '' || $paymentRequirement === '') {
            return new JsonResponse(['valid' => false, 'error' => 'Missing payment data'], 400);
        }

        $result = $this->verifier->verify($paymentSignature, $paymentRequirement, $config);

        if ($result['valid']) {
            // Settle the payment
            $settlement = $this->verifier->settle($paymentSignature, $paymentRequirement, $config);

            $pageUid = $this->requestAttributeResolver->getPageUid($this->request);
            $contentInfo = $this->contentTypeResolver->resolve($this->request, $pageUid);

            $this->paymentLogger->logPayment(
                request: $this->request,
                pageUid: $pageUid,
                amount: $this->nonEmptyString($body['price'] ?? null, $config->defaultPrice),
                currency: $config->currency,
                network: $config->network,
                txHash: $settlement['txHash'] ?? null,
                status: $settlement['settled'] ? 'settled' : 'pending',
                settlementDetails: $settlement,
                contentType: $contentInfo['type'],
                contentUid: $contentInfo['uid'],
            );

            return new JsonResponse([
                'valid' => true,
                'settled' => $settlement['settled'],
                'txHash' => $settlement['txHash'] ?? null,
            ]);
        }

        return new JsonResponse(['valid' => false, 'error' => $result['error']], 402);
    }

    private function getNetworkLabel(string $network): string
    {
        return match ($network) {
            'base' => 'Base (Mainnet)',
            'base-sepolia' => 'Base (Sepolia Testnet)',
            'polygon' => 'Polygon',
            'ethereum' => 'Ethereum',
            default => $network,
        };
    }

    private function nonEmptyString(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $stringValue = (string)$value;

        return $stringValue !== '' ? $stringValue : $default;
    }
}
