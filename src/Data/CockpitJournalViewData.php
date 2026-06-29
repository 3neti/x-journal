<?php

namespace LBHurtado\XJournal\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class CockpitJournalViewData extends Data
{
    /**
     * @param  Collection<int, CockpitJournalEntryData>  $entries
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Collection $entries,
        public int $retrievedTotal,
        public int $visibleTotal,
        public int $limit,
        public int $offset,
        public bool $hasMore,
        public array $context = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array{
     *     entries: array<int, array<string, mixed>>,
     *     retrieved_total: int,
     *     visible_total: int,
     *     limit: int,
     *     offset: int,
     *     has_more: bool,
     *     context: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries
                ->map(fn (CockpitJournalEntryData $entry): array => $entry->toArray())
                ->values()
                ->all(),
            'retrieved_total' => $this->retrievedTotal,
            'visible_total' => $this->visibleTotal,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->hasMore,
            'context' => $this->context,
            'metadata' => $this->metadata,
        ];
    }
}
