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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\ReportingPeriod;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * "x402 Paywall > Dashboard": revenue, settlement states, recent payments and the paywall
 * configuration of every site, with the mistakes that keep payments from working.
 */
#[AsController]
final readonly class PaywallDashboardController
{
    public const string ROUTE = 'web_x402_paywall_dashboard';

    private const int RECENT_TRANSACTIONS = 20;
    private const int TOP_PAGES = 10;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PaymentLogger $paymentLogger,
        private SiteFinder $siteFinder,
        private ConfigurationProvider $configurationProvider,
        private UriBuilder $uriBuilder,
        private ModuleLabels $labels,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $title = $this->labels->get('dashboard.title');
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($title);
        $view->makeDocHeaderModuleMenu();
        $view->getDocHeaderComponent()->setShortcutContext(self::ROUTE, $title);

        $periods = [];
        foreach (ReportingPeriod::cases() as $period) {
            $periods[] = [
                'label' => $this->labels->get($period->label()),
                'revenue' => $this->paymentLogger->getRevenueByCurrency($period->since()),
            ];
        }

        $view->assignMultiple([
            'periods' => $periods,
            'statusCounts' => $this->paymentLogger->countByStatus(ReportingPeriod::Last30Days->since()),
            'sites' => $this->siteSummaries(),
            'recentTransactions' => array_map($this->transaction(...), $this->paymentLogger->getRecentTransactions(self::RECENT_TRANSACTIONS)),
            'topPages' => array_map($this->topPage(...), $this->paymentLogger->getTopPages(self::TOP_PAGES, ReportingPeriod::Last30Days->since())),
            'simulatorUri' => (string)$this->uriBuilder->buildUriFromRoute(PaywallSimulatorController::ROUTE),
            'dateFormat' => ScalarValue::string($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? null, 'd-m-y')
                . ' ' . ScalarValue::string($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? null, 'H:i'),
        ]);

        return $view->renderResponse('Dashboard/Main');
    }

    /**
     * Sites with an "x402_paywall" block in their configuration.
     *
     * @return list<array<string, mixed>>
     */
    private function siteSummaries(): array
    {
        $summaries = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            if (!is_array($site->getConfiguration()['x402_paywall'] ?? null)) {
                continue;
            }
            $config = $this->configurationProvider->getForSite($site);
            $summaries[] = [
                'identifier' => $site->getIdentifier(),
                'title' => self::siteTitle($site),
                'base' => (string)$site->getBase(),
                'enabled' => $config->enabled,
                'active' => $config->isValid(),
                'network' => $config->getNetworkLabel(),
                'caip2' => $config->getCaip2NetworkId(),
                'price' => $config->defaultPrice,
                'currency' => $config->currency,
                'wallet' => $config->walletAddress,
                'facilitatorHost' => ScalarValue::string(parse_url($config->facilitatorUrl, PHP_URL_HOST), $config->facilitatorUrl),
                'cdp' => $config->usesCdpAuthentication(),
                'legacyV1' => $config->legacyV1,
                'problems' => array_map(fn(string $problem): string => $this->labels->get('problem.' . $problem), $config->getProblems()),
                'simulatorUri' => (string)$this->uriBuilder->buildUriFromRoute(PaywallSimulatorController::ROUTE, ['site' => $site->getIdentifier()]),
            ];
        }

        return $summaries;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transaction(array $row): array
    {
        $network = ScalarValue::string($row['network'] ?? null);
        $transaction = ScalarValue::string($row['tx_hash'] ?? null);
        $payer = ScalarValue::string($row['payer_address'] ?? null);
        $pageUid = ScalarValue::int($row['page_uid'] ?? null);

        return [
            'uid' => ScalarValue::int($row['uid'] ?? null),
            'time' => ScalarValue::int($row['crdate'] ?? null),
            'pageUid' => $pageUid,
            'pageTitle' => self::pageTitle($pageUid),
            'amount' => ScalarValue::string($row['amount'] ?? null),
            'currency' => ScalarValue::string($row['currency'] ?? null),
            'network' => PaywallConfiguration::networkLabel($network),
            'transaction' => $transaction,
            'transactionShort' => self::shorten($transaction),
            'transactionUrl' => PaywallConfiguration::transactionUrl($network, $transaction),
            'payer' => $payer,
            'payerShort' => self::shorten($payer),
            'status' => ScalarValue::string($row['status'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{uid: int, title: string, transactions: int, revenue: string}
     */
    private function topPage(array $row): array
    {
        $uid = ScalarValue::int($row['page_uid'] ?? null);

        return [
            'uid' => $uid,
            'title' => self::pageTitle($uid),
            'transactions' => ScalarValue::int($row['transactions'] ?? null),
            'revenue' => rtrim(rtrim(number_format(ScalarValue::float($row['revenue'] ?? null), 6, '.', ''), '0'), '.'),
        ];
    }

    private static function siteTitle(Site $site): string
    {
        $websiteTitle = ScalarValue::string($site->getConfiguration()['websiteTitle'] ?? null);

        return $websiteTitle !== '' ? $websiteTitle : self::pageTitle($site->getRootPageId());
    }

    private static function pageTitle(int $uid): string
    {
        return $uid > 0 ? ScalarValue::string(BackendUtility::getRecord('pages', $uid, 'title')['title'] ?? null) : '';
    }

    /**
     * "0x1234…cdef" for long hashes and addresses.
     */
    private static function shorten(string $value): string
    {
        return strlen($value) > 14 ? substr($value, 0, 6) . '…' . substr($value, -4) : $value;
    }
}
