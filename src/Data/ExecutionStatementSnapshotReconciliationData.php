<?php

namespace LBHurtado\XJournal\Data;

use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;
use Spatie\LaravelData\Data;

final class ExecutionStatementSnapshotReconciliationData extends Data
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public ExecutionStatementSnapshot $snapshot,
        public int $expectedEntriesCount,
        public int $actualEntriesCount,
        public string $expectedEntriesHash,
        public string $actualEntriesHash,
        public array $issues,
    ) {}

    public function isConsistent(): bool
    {
        return $this->issues === [];
    }
}
