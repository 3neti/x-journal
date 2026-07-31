<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;

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

    public function snapshotChainIsValid(array $snapshots): bool
    {
        return $this->verifyChainLinks($snapshots) === [];
    }

    /**
     * @param  array<int, mixed>  $snapshots
     * @return array<int, array<string, mixed>>
     */
    public function verifyChainLinks(array $snapshots): array
    {
        $issues = [];
        $ordered = $snapshots;

        usort($ordered, fn (mixed $a, mixed $b): int => $this->compareSnapshotsForVerification($a, $b));

        $previousHash = null;

        foreach ($ordered as $index => $snapshot) {
            if (! $snapshot instanceof ExecutionStatementSnapshot) {
                continue;
            }

            $expectedHash = $this->fingerprint($this->snapshotPayloadForHash($snapshot));
            if ((string) $snapshot->hash !== $expectedHash) {
                $issues[] = [
                    'index' => $index,
                    'statement_number' => (string) $snapshot->statement_number,
                    'code' => 'hash_mismatch',
                    'expected' => $expectedHash,
                    'actual' => $snapshot->hash,
                ];
            }

            if ($index > 0 && $snapshot->previous_hash !== $previousHash) {
                $issues[] = [
                    'index' => $index,
                    'statement_number' => (string) $snapshot->statement_number,
                    'code' => 'previous_hash_mismatch',
                    'expected' => $previousHash,
                    'actual' => $snapshot->previous_hash,
                ];
            }

            $previousHash = $snapshot->hash;
        }

        return $issues;
    }

    protected function compareSnapshotsForVerification(mixed $a, mixed $b): int
    {
        if (! $a instanceof ExecutionStatementSnapshot || ! $b instanceof ExecutionStatementSnapshot) {
            return 0;
        }

        if ($a->generated_at->toDateTimeString() === $b->generated_at->toDateTimeString()) {
            return $a->id <=> $b->id;
        }

        return $a->generated_at <=> $b->generated_at;
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotPayloadForHash(ExecutionStatementSnapshot $snapshot): array
    {
        return [
            'statement_number' => $snapshot->statement_number,
            'statement_type' => $snapshot->statement_type,
            'period_start' => $snapshot->period_start?->toJSON(),
            'period_end' => $snapshot->period_end?->toJSON(),
            'subject_type' => $snapshot->subject_type,
            'subject_id' => $snapshot->subject_id,
            'opening_json' => $snapshot->opening_json,
            'activity_json' => $snapshot->activity_json,
            'closing_json' => $snapshot->closing_json,
            'entries_count' => $snapshot->entries_count,
            'entries_hash' => $snapshot->entries_hash,
            'generated_at' => $snapshot->generated_at?->toJSON(),
            'generated_by_type' => $snapshot->generated_by_type,
            'generated_by_id' => $snapshot->generated_by_id,
            'payload_json' => $snapshot->payload_json,
            'previous_hash' => $snapshot->previous_hash,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
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
