<?php

namespace LBHurtado\XJournal\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotQueryData;
use LBHurtado\XJournal\Services\ExecutionStatementSnapshotVerifier;

class VerifySnapshotsCommand extends Command
{
    protected $signature = 'x-journal:verify-snapshots
                            {--statement-type= : Optional statement type to scope verification}
                            {--subject-type= : Optional subject type to scope verification}
                            {--subject-id= : Optional subject id to scope verification}
                            {--statement-number= : Optional statement number to scope verification}
                            {--json : Output verification result as JSON}';

    protected $description = 'Verify statement snapshots integrity and reconciliation state';

    public function handle(ExecutionStatementSnapshotVerifier $verifier): int
    {
        $verification = $verifier->verifyAll($this->buildQuery());

        $this->render($verification);

        return $verification->verified ? self::SUCCESS : self::FAILURE;
    }

    protected function buildQuery(): ExecutionStatementSnapshotQueryData
    {
        $statementType = $this->option('statement-type');
        $subjectType = $this->option('subject-type');
        $subjectId = $this->option('subject-id');
        $statementNumber = $this->option('statement-number');

        return new ExecutionStatementSnapshotQueryData(
            statementType: is_string($statementType) && trim($statementType) !== '' ? $statementType : null,
            subjectType: is_string($subjectType) && trim($subjectType) !== '' ? $subjectType : null,
            subjectId: is_string($subjectId) && trim($subjectId) !== '' ? $subjectId : null,
            statementNumber: is_string($statementNumber) && trim($statementNumber) !== '' ? $statementNumber : null,
        );
    }

    protected function render($verification): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($verification->toArray(), JSON_THROW_ON_ERROR));

            return;
        }

        $status = $verification->verified ? 'verified' : 'unverified';
        $this->line(sprintf('Snapshot verification status: %s', $status));
        $this->line(sprintf('Checked snapshots: %d', $verification->checkedSnapshotCount));

        $issueCount = count($verification->issues);
        if ($issueCount === 0) {
            $this->info('No issues found.');

            return;
        }

        $this->warn(sprintf('Found %d issue(s).', $issueCount));
        foreach ($verification->issues as $index => $issue) {
            $this->line(sprintf('  %d) [%s] %s', $index + 1, $issue->code, $issue->message));
        }
    }
}
