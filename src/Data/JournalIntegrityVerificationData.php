<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class JournalIntegrityVerificationData extends Data
{
    /**
     * @param  array<int, JournalIntegrityIssueData>  $issues
     */
    public function __construct(
        public bool $verified,
        public int $checkedEntryCount,
        public array $issues = [],
    ) {}

    /**
     * @return array{verified: bool, checked_entry_count: int, issues: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'verified' => $this->verified,
            'checked_entry_count' => $this->checkedEntryCount,
            'issues' => array_map(
                fn (JournalIntegrityIssueData $issue): array => $issue->toArray(),
                $this->issues,
            ),
        ];
    }
}
