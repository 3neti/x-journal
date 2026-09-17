<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionIntegrityData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Exceptions\JournalEntryIdempotencyConflictException;
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

        return DB::transaction(function () use ($entry): ExecutionJournalEntry {
            $head = DB::table('execution_journal_heads')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($head === null) {
                throw new InvalidArgumentException('The journal integrity head has not been initialized.');
            }

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
                        $existing->reference_number,
                    );
                }
            }

            $integrity = ($entry->integrity ?? new ExecutionIntegrityData)->toArray();
            $integrity['previous_hash'] ??= is_string($head->current_hash) ? $head->current_hash : null;
            $integrity['hash'] ??= $this->integrityHasher->hash($entry, $integrity);

            $record = ExecutionJournalEntry::query()->create([
                'reference_number' => $entry->referenceNumber,
                'event_type' => $entry->eventType,
                'source_system' => $entry->sourceSystem,
                'source_event_id' => $entry->sourceEventId,
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

            DB::table('execution_journal_heads')
                ->where('id', 1)
                ->update([
                    'current_hash' => $integrity['hash'],
                    'updated_at' => now(),
                ]);

            return $record;
        });
    }
}
