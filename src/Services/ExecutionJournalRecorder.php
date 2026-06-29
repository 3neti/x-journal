<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ExecutionJournalRecorder
{
    public function __construct(
        protected JournalSinkContract $sink,
        protected ExecutionReferenceNumberGenerator $referenceNumberGenerator,
    ) {}

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        if ($entry->referenceNumber !== null) {
            return $this->sink->record($entry);
        }

        return $this->sink->record(
            $entry->withReferenceNumber(
                $this->referenceNumberGenerator->generate($entry->occurredAt)
            )
        );
    }
}
