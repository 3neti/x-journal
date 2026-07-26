<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ExecutionJournalIntegrityHasher
{
    public function previousHash(): ?string
    {
        $latest = ExecutionJournalEntry::query()
            ->latest('id')
            ->value('integrity');

        if (is_string($latest)) {
            $decoded = json_decode($latest, true);

            return is_array($decoded) && is_string($decoded['hash'] ?? null)
                ? $decoded['hash']
                : null;
        }

        return is_array($latest) && is_string($latest['hash'] ?? null)
            ? $latest['hash']
            : null;
    }

    /**
     * @param  array<string, mixed>  $integrity
     */
    public function hash(ExecutionJournalEntryData $entry, array $integrity): string
    {
        return hash('sha256', $this->canonicalJson($entry, $integrity));
    }

    /**
     * @param  array<string, mixed>  $integrity
     */
    public function canonicalJson(
        ExecutionJournalEntryData $entry,
        array $integrity,
    ): string {
        $canonical = [
            'reference_number' => $entry->referenceNumber,
            'event_type' => $entry->eventType,
            'occurred_at' => $entry->occurredAt->toJSON(),
            'actor' => $entry->actor->toArray(),
            'subject' => $entry->subject->toArray(),
            'money' => $entry->money?->toArray(),
            'references' => $entry->references->toArray(),
            'payload' => $entry->payload,
            'metadata' => $entry->metadata,
            'previous_hash' => $integrity['previous_hash'] ?? null,
        ];

        return json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
}
