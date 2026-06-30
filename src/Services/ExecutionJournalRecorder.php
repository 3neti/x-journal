<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Exceptions\JournalEntryIdempotencyConflictException;

class ExecutionJournalRecorder
{
    public function __construct(
        protected JournalSinkContract $sink,
        protected ExecutionReferenceNumberGenerator $referenceNumberGenerator,
        protected ExecutionJournalIdempotencyHasher $idempotencyHasher,
    ) {}

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        if (config('x-journal.idempotency.enabled', true) && $entry->idempotencyKey !== null) {
            $existing = ExecutionJournalEntry::query()
                ->where('idempotency_key', $entry->idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ((string) $existing->idempotency_fingerprint === $this->idempotencyHasher->fingerprint($entry)) {
                    return $existing;
                }

                throw JournalEntryIdempotencyConflictException::forEntryMismatch(
                    $entry->idempotencyKey,
                    $existing->reference_number
                );
            }
        }

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
