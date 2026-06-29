<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Contracts\SecondaryJournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalSinkDispatcher implements JournalSinkContract
{
    /**
     * @param  array<int, SecondaryJournalSinkContract>  $secondarySinks
     */
    public function __construct(
        protected DatabaseJournalSink $canonicalSink,
        protected array $secondarySinks = [],
    ) {}

    public function addSecondarySink(SecondaryJournalSinkContract $sink): self
    {
        $this->secondarySinks[] = $sink;

        return $this;
    }

    /**
     * @return array<int, SecondaryJournalSinkContract>
     */
    public function secondarySinks(): array
    {
        return $this->secondarySinks;
    }

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        $canonicalEntry = $this->canonicalSink->record($entry);

        foreach ($this->secondarySinks as $secondarySink) {
            $secondarySink->recordProjection($canonicalEntry, $entry);
        }

        return $canonicalEntry;
    }
}
