<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Data\ExecutionJournalEntryData;

class ExecutionJournalIdempotencyHasher
{
    public function fingerprint(ExecutionJournalEntryData $entry): string
    {
        $canonical = [
            'event_type' => $entry->eventType,
            'occurred_at' => $entry->occurredAt->toJSON(),
            'actor' => $entry->actor->toArray(),
            'subject' => $entry->subject->toArray(),
            'money' => $entry->money?->toArray(),
            'references' => $entry->references->toArray(),
            'payload' => $entry->payload,
            'metadata' => $entry->metadata,
        ];

        return hash('sha256', json_encode($this->normalize($canonical), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected function normalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeNested($item);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected function normalizeNested(array $value): array
    {
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $value[$index] = $this->normalizeNested($item);
                }
            }

            return $value;
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeNested($item);
            }
        }

        return $value;
    }
}
