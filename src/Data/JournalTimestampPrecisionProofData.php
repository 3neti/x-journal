<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class JournalTimestampPrecisionProofData extends Data
{
    public function __construct(
        public bool $proved,
        public string $referenceNumber,
        public ?string $actualHash,
        public ?string $persistedExpectedHash,
        public int $candidateCount,
        public ?int $recoveredMicroseconds,
        public ?string $recoveredOccurredAt,
        public bool $idempotencyFingerprintMatched,
    ) {}
}
