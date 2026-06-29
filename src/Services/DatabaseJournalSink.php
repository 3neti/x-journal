<?php

namespace LBHurtado\XJournal\Services;

use InvalidArgumentException;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionIntegrityData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class DatabaseJournalSink implements JournalSinkContract
{
    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        if ($entry->referenceNumber === null) {
            throw new InvalidArgumentException('Journal entries must have a reference number before persistence.');
        }

        return ExecutionJournalEntry::query()->create([
            'reference_number' => $entry->referenceNumber,
            'event_type' => $entry->eventType,
            'occurred_at' => $entry->occurredAt,
            'actor' => $entry->actor->toArray(),
            'subject' => $entry->subject->toArray(),
            'money' => $entry->money?->toArray(),
            'references' => $entry->references->toArray(),
            'payload' => $entry->payload,
            'integrity' => ($entry->integrity ?? new ExecutionIntegrityData)->toArray(),
            'metadata' => $entry->metadata,
        ]);
    }
}
