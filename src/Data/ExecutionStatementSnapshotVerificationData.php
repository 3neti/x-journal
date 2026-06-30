<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionStatementSnapshotVerificationData extends Data
{
    /**
     * @param  array<int, ExecutionStatementSnapshotVerificationIssueData>  $issues
     */
    public function __construct(
        public bool $verified,
        public int $checkedSnapshotCount,
        public array $issues = [],
        public array $metadata = [],
    ) {}

    public function isVerified(): bool
    {
        return $this->verified;
    }

    /**
     * @return array{
     *   verified: bool,
     *   checked_snapshot_count: int,
     *   issues: array<int, array<string, mixed>>,
     *   metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'verified' => $this->verified,
            'checked_snapshot_count' => $this->checkedSnapshotCount,
            'issues' => array_map(
                fn (ExecutionStatementSnapshotVerificationIssueData $issue): array => $issue->toArray(),
                $this->issues,
            ),
            'metadata' => $this->metadata,
        ];
    }
}
