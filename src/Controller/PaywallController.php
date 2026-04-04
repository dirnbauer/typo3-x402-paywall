<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Service\PaymentVerifier;

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
        $pageInfo = $this->request->getAttribute('frontend.page.information');
        $pageRecord = $pageInfo?->getPageRecord() ?? [];
        $isGated = (bool)($pageRecord['tx_x402_paywall_enabled'] ?? false);

        if (!$isGated) {
            $this->view->assign('paywallActive', false);
            return $this->htmlResponse();
        }

        $price = ($pageRecord['tx_x402_paywall_price'] ?? '') ?: $config->defaultPrice;
        $description = ($pageRecord['tx_x402_paywall_description'] ?? '') ?: ($pageRecord['title'] ?? '');

        // Build payment requirement for the frontend JavaScript
        $paymentRequirement = [
            'scheme' => 'exact',
            'network' => $config->getCaip2NetworkId(),
            'maxAmountRequired' => $this->toBaseUnits($price, 6),
            'resource' => (string)$this->request->getUri(),
            'description' => $description,
            'maxTimeoutSeconds' => 300,
            'payTo' => $config->walletAddress,
            'asset' => $this->getAsset($config),
        ];

        $this->view->assignMultiple([
            'paywallActive' => true,
            'price' => $price,
            'currency' => $config->currency,
            'description' => $description,
            'network' => $config->network,
            'networkLabel' => $this->getNetworkLabel($config->network),
            'freePreviewParagraphs' => $config->freePreviewParagraphs,
            'paymentRequirement' => json_encode($paymentRequirement),
            'paymentRequirementBase64' => base64_encode(json_encode($paymentRequirement)),
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
        $body = json_decode((string)$this->request->getBody(), true);

        $paymentSignature = $body['paymentSignature'] ?? '';
        $paymentRequirement = $body['paymentRequirement'] ?? '';

        if (!$paymentSignature || !$paymentRequirement) {
            return new JsonResponse(['valid' => false, 'error' => 'Missing payment data'], 400);
        }

        $result = $this->verifier->verify($paymentSignature, $paymentRequirement, $config);

        if ($result['valid']) {
            // Settle the payment
            $settlement = $this->verifier->settle($paymentSignature, $paymentRequirement, $config);
            return new JsonResponse([
                'valid' => true,
                'settled' => $settlement['settled'],
                'txHash' => $settlement['txHash'] ?? null,
            ]);
        }

        return new JsonResponse(['valid' => false, 'error' => $result['error'] ?? 'Verification failed'], 402);
    }

    private function toBaseUnits(string $amount, int $decimals): string
    {
        $parts = explode('.', $amount, 2);
        $integer = $parts[0];
        $fraction = str_pad($parts[1] ?? '', $decimals, '0');
        $fraction = substr($fraction, 0, $decimals);
        return ltrim($integer . $fraction, '0') ?: '0';
    }

    /**
     * @return array{address: string, symbol: string, decimals: int}
     */
    private function getAsset(\Webconsulting\X402Paywall\Configuration\PaywallConfiguration $config): array
    {
        $usdcAddresses = [
            'base' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
            'base-sepolia' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            'polygon' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ];

        return [
            'address' => $usdcAddresses[$config->network] ?? $usdcAddresses['base-sepolia'],
            'symbol' => $config->currency,
            'decimals' => 6,
        ];
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
}
