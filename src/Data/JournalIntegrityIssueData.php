<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class JournalIntegrityIssueData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public ?string $referenceNumber,
        public string $message,
        public mixed $expected = null,
        public mixed $actual = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array{code: string, reference_number: ?string, message: string, expected: mixed, actual: mixed, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'reference_number' => $this->referenceNumber,
            'message' => $this->message,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'metadata' => $this->metadata,
        ];
    }
}
