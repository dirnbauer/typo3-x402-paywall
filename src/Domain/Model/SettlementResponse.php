<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Domain\Model;

use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 v2 "SettlementResponse": the facilitator's answer to POST /settle, transported
 * base64-encoded in the PAYMENT-RESPONSE header of the paid resource.
 */
final readonly class SettlementResponse
{
    /**
     * @param array<string, mixed> $raw Complete facilitator response as received (kept for the payment log)
     */
    public function __construct(
        public bool $success,
        public string $transaction = '',
        public string $network = '',
        public string $payer = '',
        public string $amount = '',
        public string $errorReason = '',
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $body Facilitator response
     * @param string $defaultNetwork Used when the facilitator omits the network
     * @param string $defaultPayer Used when the facilitator omits the payer (the address that signed the authorization)
     */
    public static function fromArray(array $body, string $defaultNetwork = '', string $defaultPayer = ''): self
    {
        $success = ($body['success'] ?? null) === true;

        return new self(
            success: $success,
            transaction: ScalarValue::string($body['transaction'] ?? null),
            network: ScalarValue::string($body['network'] ?? null, $defaultNetwork),
            payer: ScalarValue::string($body['payer'] ?? null, $defaultPayer),
            amount: ScalarValue::string($body['amount'] ?? null),
            errorReason: $success ? '' : ScalarValue::string($body['errorReason'] ?? $body['error'] ?? $body['message'] ?? null, 'settlement_failed'),
            raw: $body,
        );
    }

    public static function failed(string $errorReason, string $network, string $payer = ''): self
    {
        return new self(success: false, network: $network, payer: $payer, errorReason: $errorReason);
    }

    /**
     * @throws \InvalidArgumentException when the value is not a base64-encoded JSON document
     */
    public static function fromHeaderValue(string $base64): self
    {
        return self::fromArray(Json::object(HeaderDocument::decode($base64, 'PAYMENT-RESPONSE')));
    }

    /**
     * Wire format; optional fields are omitted when empty.
     *
     * @return array{success: bool, errorReason?: string, transaction: string, network: string, payer?: string, amount?: string}
     */
    public function toArray(): array
    {
        $data = ['success' => $this->success];
        if (!$this->success && $this->errorReason !== '') {
            $data['errorReason'] = $this->errorReason;
        }
        $data['transaction'] = $this->transaction;
        $data['network'] = $this->network;
        if ($this->payer !== '') {
            $data['payer'] = $this->payer;
        }
        if ($this->amount !== '') {
            $data['amount'] = $this->amount;
        }

        return $data;
    }

    public function toHeaderValue(): string
    {
        return base64_encode(Json::encode($this->toArray()));
    }
}
