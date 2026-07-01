<?php

namespace LBHurtado\XJournal\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XJournal\Data\JournalIntegrityVerificationData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

class VerifyJournalCommand extends Command
{
    protected $signature = 'x-journal:verify-integrity
                            {reference? : Optional journal entry reference number to verify up to this entry}
                            {--json : Output verification result as JSON}';

    protected $description = 'Verify execution journal integrity and chain continuity';

    public function handle(JournalIntegrityVerifier $verifier): int
    {
        $reference = $this->argument('reference');

        if (! is_string($reference) || $reference === '') {
            $verification = $verifier->verify();
        } else {
            $entry = ExecutionJournalEntry::query()->where('reference_number', $reference)->first();

            if (! $entry instanceof ExecutionJournalEntry) {
                $this->error(sprintf('Reference %s not found', $reference));

                return self::FAILURE;
            }

            $verification = $verifier->verify(
                ExecutionJournalEntry::query()
                    ->where('id', '<=', (int) $entry->id)
                    ->orderBy('id')
                    ->get(),
            );
        }

        $this->render($verification);

        return $verification->verified ? self::SUCCESS : self::FAILURE;
    }

    protected function render(JournalIntegrityVerificationData $verification): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($verification->toArray(), JSON_THROW_ON_ERROR));

            return;
        }

        $status = $verification->verified ? 'verified' : 'unverified';
        $this->line(sprintf('Journal integrity status: %s', $status));
        $this->line(sprintf('Checked entries: %d', $verification->checkedEntryCount));

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
