<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotQueryData;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotVerificationData;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotVerificationIssueData;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;

class ExecutionStatementSnapshotVerifier
{
    public function __construct(
        protected ExecutionStatementSnapshotHasher $hasher,
        protected ExecutionStatementSnapshotReconciler $reconciler,
        protected ExecutionStatementSnapshotRetriever $retriever,
    ) {}

    public function verifyAll(?ExecutionStatementSnapshotQueryData $query = null): ExecutionStatementSnapshotVerificationData
    {
        $query ??= new ExecutionStatementSnapshotQueryData;
        $snapshots = $this->orderedQueryForVerification($query)->get();

        $issues = [];
        $chainIssues = $this->hasher->verifyChainLinks($snapshots->all());

        foreach ($chainIssues as $chainIssue) {
            $issues[] = $this->issueFromChainFailure($chainIssue);
        }

        foreach ($snapshots as $snapshot) {
            if (! $snapshot instanceof ExecutionStatementSnapshot) {
                continue;
            }

            $reconciliation = $this->reconciler->reconcile($snapshot);
            foreach ($reconciliation->issues as $code) {
                $issues[] = match ($code) {
                    'entries_count_mismatch' => new ExecutionStatementSnapshotVerificationIssueData(
                        code: 'entries_count_mismatch',
                        statementNumber: $snapshot->statement_number,
                        message: 'Snapshot entries count does not match period journal entries.',
                        expected: $reconciliation->expectedEntriesCount,
                        actual: $reconciliation->actualEntriesCount,
                        metadata: ['snapshot_id' => $snapshot->getKey()],
                    ),
                    'entries_hash_mismatch' => new ExecutionStatementSnapshotVerificationIssueData(
                        code: 'entries_hash_mismatch',
                        statementNumber: $snapshot->statement_number,
                        message: 'Snapshot entries hash does not match period journal entries hash.',
                        expected: $reconciliation->expectedEntriesHash,
                        actual: $reconciliation->actualEntriesHash,
                        metadata: ['snapshot_id' => $snapshot->getKey()],
                    ),
                    default => new ExecutionStatementSnapshotVerificationIssueData(
                        code: 'reconciliation_error',
                        statementNumber: $snapshot->statement_number,
                        message: 'Snapshot reconciliation reported an unknown issue.',
                        expected: null,
                        actual: $code,
                        metadata: ['snapshot_id' => $snapshot->getKey()],
                    ),
                };
            }
        }

        return new ExecutionStatementSnapshotVerificationData(
            verified: $issues === [],
            checkedSnapshotCount: $snapshots->count(),
            issues: $issues,
            metadata: [
                'checked_snapshot_count' => $snapshots->count(),
                'issue_count' => count($issues),
            ],
        );
    }

    protected function orderedQueryForVerification(ExecutionStatementSnapshotQueryData $query): Builder
    {
        return $this->retriever->orderedQueryForVerification(
            $query->order === 'desc'
                ? new ExecutionStatementSnapshotQueryData(
                    statementType: $query->statementType,
                    subjectType: $query->subjectType,
                    subjectId: $query->subjectId,
                    statementNumber: $query->statementNumber,
                    generatedAfter: $query->generatedAfter,
                    generatedBefore: $query->generatedBefore,
                    limit: PHP_INT_MAX,
                    offset: 0,
                    order: 'asc',
                )
                : $query,
        );
    }

    protected function issueFromChainFailure(array $chainIssue): ExecutionStatementSnapshotVerificationIssueData
    {
        $code = $chainIssue['code'] ?? 'chain_verification_error';
        $statementNumber = $chainIssue['statement_number'] ?? null;

        return match ($code) {
            'hash_mismatch' => new ExecutionStatementSnapshotVerificationIssueData(
                code: 'hash_mismatch',
                statementNumber: $statementNumber,
                message: 'Snapshot hash does not match computed payload.',
                expected: $chainIssue['expected'] ?? null,
                actual: $chainIssue['actual'] ?? null,
                metadata: ['chain_index' => $chainIssue['index'] ?? null],
            ),
            'previous_hash_mismatch' => new ExecutionStatementSnapshotVerificationIssueData(
                code: 'previous_hash_mismatch',
                statementNumber: $statementNumber,
                message: 'Snapshot previous hash does not link to prior snapshot hash.',
                expected: $chainIssue['expected'] ?? null,
                actual: $chainIssue['actual'] ?? null,
                metadata: ['chain_index' => $chainIssue['index'] ?? null],
            ),
            default => new ExecutionStatementSnapshotVerificationIssueData(
                code: 'chain_verification_error',
                statementNumber: $statementNumber,
                message: 'Snapshot chain verification returned an unknown issue.',
                expected: null,
                actual: $chainIssue,
                metadata: ['chain_index' => $chainIssue['index'] ?? null],
            ),
        };
    }
}
