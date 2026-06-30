<?php

namespace LBHurtado\XJournal\Contracts;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Data\JournalIntegrityIssueData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface JournalIntegrityVerificationMetadataContract
{
    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     * @param  array<int, JournalIntegrityIssueData>  $issues
     */
    public function collect(Collection $entries, array $issues): array;
}
