<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ExecutionStatementSnapshotHasher
{
    public function entriesHash(Collection $entries): string
    {
        return hash('sha256', json_encode($this->normalize(
            $entries->map(function (ExecutionJournalEntry $entry): array {
                return [
                    'reference_number' => $entry->reference_number,
                    'event_type' => $entry->event_type,
                    'occurred_at' => $entry->occurred_at?->toJSON(),
                    'subject_type' => $entry->subject_type,
                    'subject_id' => $entry->subject_id,
                    'payload' => $entry->payload,
                    'metadata' => $entry->metadata,
                ];
            })->all(),
            true,
        ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($this->normalize($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  bool  $preserveListOrder
     * @return array<string, mixed>
     */
    protected function normalize(array $value, bool $preserveListOrder = false): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeNested($item, $preserveListOrder);
            }
        }

        if (! $preserveListOrder) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  bool  $preserveListOrder
     * @return array<string, mixed>
     */
    protected function normalizeNested(array $value, bool $preserveListOrder = false): array
    {
        if (array_is_list($value)) {
            if (! $preserveListOrder) {
                sort($value, SORT_REGULAR);
            }

            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $value[$index] = $this->normalizeNested($item, $preserveListOrder);
                }
            }

            return $value;
        }

        if (! $preserveListOrder) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeNested($item, $preserveListOrder);
            }
        }

        return $value;
    }
}
