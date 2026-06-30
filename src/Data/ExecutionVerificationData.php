<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionVerificationData extends Data
{
    /**
     * @param  array<string>  $levels
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $referenceNumber,
        public string $status,
        public string $verificationUrl,
        public ?string $verificationToken,
        public bool $chainVerified,
        public ?string $receiptHash,
        public array $levels,
        public array $metadata = [],
    ) {}

    /**
     * @return array{reference_number: string, status: string, verification_url: string, verification_token: ?string, chain_verified: bool, receipt_hash: ?string, levels: array<string>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'reference_number' => $this->referenceNumber,
            'status' => $this->status,
            'verification_url' => $this->verificationUrl,
            'verification_token' => $this->verificationToken,
            'chain_verified' => $this->chainVerified,
            'receipt_hash' => $this->receiptHash,
            'levels' => $this->levels,
            'metadata' => $this->metadata,
        ];
    }
}
