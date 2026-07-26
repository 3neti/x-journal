<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Contracts\JournalIdempotencyKeyResolverContract;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Exceptions\JournalEntryIdempotencyConflictException;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ExecutionJournalRecorder
{
    public function __construct(
        protected JournalSinkContract $sink,
        protected ExecutionReferenceNumberGenerator $referenceNumberGenerator,
        protected ExecutionJournalIdempotencyHasher $idempotencyHasher,
        protected JournalIdempotencyKeyResolverContract $idempotencyKeyResolver,
    ) {}

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        $entry = $entry->withOccurredAt(
            CarbonImmutable::instance($entry->occurredAt)->startOfSecond(),
        );
        $entry = $entry->withIdempotencyKey(
            $this->idempotencyKeyResolver->resolve($entry->idempotencyKey, $entry)
        );

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
