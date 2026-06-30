<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionStatementSnapshotVerificationIssueData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public ?string $statementNumber,
        public string $message,
        public mixed $expected = null,
        public mixed $actual = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array{
     *     code: string,
     *     statement_number: ?string,
     *     message: string,
     *     expected: mixed,
     *     actual: mixed,
     *     metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'statement_number' => $this->statementNumber,
            'message' => $this->message,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'metadata' => $this->metadata,
        ];
    }
}
