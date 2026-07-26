<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionIntegrityData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class DatabaseJournalSink implements JournalSinkContract
{
    public function __construct(
        protected ExecutionJournalIntegrityHasher $integrityHasher,
        protected ExecutionJournalIdempotencyHasher $idempotencyHasher,
    ) {}

    public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
    {
        $entry = $entry->withOccurredAt(
            CarbonImmutable::instance($entry->occurredAt)->startOfSecond(),
        );

        if ($entry->referenceNumber === null) {
            throw new InvalidArgumentException('Journal entries must have a reference number before persistence.');
        }

        $integrity = ($entry->integrity ?? new ExecutionIntegrityData)->toArray();
        $integrity['previous_hash'] ??= $this->integrityHasher->previousHash();
        $integrity['hash'] ??= $this->integrityHasher->hash($entry, $integrity);

        return ExecutionJournalEntry::query()->create([
            'reference_number' => $entry->referenceNumber,
            'event_type' => $entry->eventType,
            'occurred_at' => $entry->occurredAt,
            'actor_type' => $entry->actor->type,
            'actor_id' => $entry->actor->id,
            'subject_type' => $entry->subject->type,
            'subject_id' => $entry->subject->id,
            'correlation_id' => $entry->references->correlationId,
            'causation_id' => $entry->references->causationId,
            'execution_id' => $entry->references->executionId,
            'actor' => $entry->actor->toArray(),
            'subject' => $entry->subject->toArray(),
            'money' => $entry->money?->toArray(),
            'references' => $entry->references->toArray(),
            'payload' => $entry->payload,
            'integrity' => $integrity,
            'metadata' => $entry->metadata,
            'idempotency_key' => $entry->idempotencyKey,
            'idempotency_fingerprint' => $entry->idempotencyKey !== null
                ? $this->idempotencyHasher->fingerprint($entry)
                : null,
        ]);
    }
}
