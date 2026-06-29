<?php

namespace LBHurtado\XJournal\Data;

use Illuminate\Support\Collection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Spatie\LaravelData\Data;

final class JournalRetrievalResultData extends Data
{
    /**
     * @param  Collection<int, ExecutionJournalEntry>  $entries
     */
    public function __construct(
        public Collection $entries,
        public int $total,
        public int $limit,
        public int $offset,
    ) {}

    public function hasMore(): bool
    {
        return $this->offset + $this->entries->count() < $this->total;
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, total: int, limit: int, offset: int, has_more: bool}
     */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries
                ->map(fn (ExecutionJournalEntry $entry): array => $entry->toArray())
                ->values()
                ->all(),
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->hasMore(),
        ];
    }
}
