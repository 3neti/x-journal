<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\ExecutionStatementSnapshotReconciliationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;

class ExecutionStatementSnapshotReconciler
{
    public function __construct(
        protected ExecutionStatementSnapshotHasher $hasher,
    ) {}

    public function reconcile(ExecutionStatementSnapshot $snapshot): ExecutionStatementSnapshotReconciliationData
    {
        $entries = ExecutionJournalEntry::query()
            ->where('subject_type', $snapshot->subject_type)
            ->where('subject_id', $snapshot->subject_id)
            ->where('occurred_at', '>=', $snapshot->period_start)
            ->where('occurred_at', '<=', $snapshot->period_end)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $actualEntriesCount = $entries->count();
        $actualEntriesHash = $this->hasher->entriesHash($entries);
        $expectedEntriesHash = (string) $snapshot->entries_hash;
        $expectedEntriesCount = (int) $snapshot->entries_count;
        $issues = [];

        if ($actualEntriesCount !== $expectedEntriesCount) {
            $issues[] = 'entries_count_mismatch';
        }

        if ($actualEntriesHash !== $expectedEntriesHash) {
            $issues[] = 'entries_hash_mismatch';
        }

        return new ExecutionStatementSnapshotReconciliationData(
            snapshot: $snapshot,
            expectedEntriesCount: $expectedEntriesCount,
            actualEntriesCount: $actualEntriesCount,
            expectedEntriesHash: $expectedEntriesHash,
            actualEntriesHash: $actualEntriesHash,
            issues: $issues,
        );
    }
}
