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
 * base64-encoded in the PAYMENT-RESPONSE header - on the paid resource and on the 402 that
 * reports a failed settlement.
 */
final readonly class SettlementResponse
{
    /** Specification v2, section 9: non-terminal, the broadcast transaction may still confirm on chain. */
    public const string SETTLEMENT_PENDING = 'settlement_pending';

    /** Specification v2, section 9: settlement failed for a reason the facilitator did not name. */
    public const string UNEXPECTED_SETTLE_ERROR = 'unexpected_settle_error';

    /**
     * @param array<string, mixed> $raw Complete facilitator response as received (kept for the payment log)
     * @param string $errorMessage Human-readable detail for a failure (the reference SDKs send it alongside errorReason)
     * @param array<string, mixed> $extensions Extension data for the buyer (passed through)
     * @param bool $outcomeUnknown The facilitator stopped answering after the request was sent: the payment
     *                             may or may not settle. Never sent to clients; the payment log records it as pending.
     */
    public function __construct(
        public bool $success,
        public string $transaction = '',
        public string $network = '',
        public string $payer = '',
        public string $amount = '',
        public string $errorReason = '',
        public array $raw = [],
        public string $errorMessage = '',
        public array $extensions = [],
        public bool $outcomeUnknown = false,
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
            errorReason: $success ? '' : ScalarValue::string($body['errorReason'] ?? null, self::UNEXPECTED_SETTLE_ERROR),
            // Facilitators without a SettleResponse body (API gateways, CDP) describe the problem in errorMessage or message.
            raw: $body,
            errorMessage: $success ? '' : ScalarValue::string($body['errorMessage'] ?? $body['message'] ?? $body['error'] ?? $body['errorType'] ?? null),
            extensions: Json::object($body['extensions'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $raw Facilitator response, if there was one
     */
    public static function failed(string $errorReason, string $network, string $payer = '', string $errorMessage = '', array $raw = []): self
    {
        return new self(success: false, network: $network, payer: $payer, errorReason: $errorReason, raw: $raw, errorMessage: $errorMessage);
    }

    /**
     * The request reached the facilitator, but no answer came back: the payment may still settle.
     */
    public static function outcomeUnknown(string $network, string $payer, string $errorMessage): self
    {
        return new self(
            success: false,
            network: $network,
            payer: $payer,
            errorReason: self::UNEXPECTED_SETTLE_ERROR,
            errorMessage: $errorMessage,
            outcomeUnknown: true,
        );
    }

    /**
     * @throws \InvalidArgumentException when the value is not a base64-encoded JSON document
     */
    public static function fromHeaderValue(string $base64): self
    {
        return self::fromArray(Json::object(HeaderDocument::decode($base64, 'PAYMENT-RESPONSE')));
    }

    /**
     * The facilitator broadcast the transaction but could not confirm it (settlement_pending with the
     * transaction hash, as the specification requires for this code).
     */
    public function isSettlementPending(): bool
    {
        return !$this->success && $this->errorReason === self::SETTLEMENT_PENDING && $this->transaction !== '';
    }

    /**
     * Neither settled nor failed: a pending settlement or an unknown outcome. The payer must not be asked
     * to pay again before the transaction is checked on chain.
     */
    public function isPending(): bool
    {
        return $this->isSettlementPending() || (!$this->success && $this->outcomeUnknown);
    }

    /**
     * Wire format; optional fields are omitted when empty.
     *
     * @return array{success: bool, errorReason?: string, errorMessage?: string, transaction: string, network: string, payer?: string, amount?: string, extensions?: \stdClass}
     */
    public function toArray(): array
    {
        $data = ['success' => $this->success];
        if (!$this->success) {
            $data['errorReason'] = $this->errorReason !== '' ? $this->errorReason : self::UNEXPECTED_SETTLE_ERROR;
            if ($this->errorMessage !== '') {
                $data['errorMessage'] = $this->errorMessage;
            }
        }
        $data['transaction'] = $this->transaction;
        $data['network'] = $this->network;
        if ($this->payer !== '') {
            $data['payer'] = $this->payer;
        }
        if ($this->amount !== '') {
            $data['amount'] = $this->amount;
        }
        if ($this->extensions !== []) {
            $data['extensions'] = (object)$this->extensions;
        }

        return $data;
    }

    public function toHeaderValue(): string
    {
        return base64_encode(Json::encode($this->toArray(), JSON_UNESCAPED_SLASHES));
    }
}
