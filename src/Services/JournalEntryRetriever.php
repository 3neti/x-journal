<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\JournalRetrievalResultData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalEntryRetriever
{
    public function findBySourceIdentity(string $sourceSystem, string $sourceEventId): ?ExecutionJournalEntry
    {
        return ExecutionJournalEntry::query()
            ->where('source_system', $sourceSystem)
            ->where('source_event_id', $sourceEventId)
            ->first();
    }

    /**
     * @return array{entries: Collection<int, ExecutionJournalEntry>, next_cursor: ?int, has_more: bool}
     */
    public function sourcePage(
        string $sourceSystem,
        int $limit,
        ?int $beforeId = null,
        ?string $eventType = null,
        ?string $subjectType = null,
    ): array {
        $limit = max(1, min(200, $limit));

        $entries = ExecutionJournalEntry::query()
            ->where('source_system', $sourceSystem)
            ->when($beforeId, fn (Builder $builder, int $id): Builder => $builder->where('id', '<', $id))
            ->when($eventType, fn (Builder $builder, string $value): Builder => $builder->where('event_type', $value))
            ->when($subjectType, fn (Builder $builder, string $value): Builder => $builder->where('subject_type', $value))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $entries->count() > $limit;
        $entries = $entries->take($limit)->values();

        return [
            'entries' => $entries,
            'next_cursor' => $hasMore ? $entries->last()?->getKey() : null,
            'has_more' => $hasMore,
        ];
    }

    public function findByReferenceNumber(string $referenceNumber): ?ExecutionJournalEntry
    {
        return ExecutionJournalEntry::query()
            ->where('reference_number', $referenceNumber)
            ->first();
    }

    public function search(JournalRetrievalQueryData $query): JournalRetrievalResultData
    {
        $builder = $this->applyFilters(ExecutionJournalEntry::query(), $query);
        $total = (clone $builder)->count();

        $entries = $builder
            ->orderBy('id', $query->order)
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new JournalRetrievalResultData(
            entries: $entries,
            total: $total,
            limit: $query->limit,
            offset: $query->offset,
        );
    }

    /**
     * @param  Builder<ExecutionJournalEntry>  $builder
     * @return Builder<ExecutionJournalEntry>
     */
    protected function applyFilters(Builder $builder, JournalRetrievalQueryData $query): Builder
    {
        return $builder
            ->when($query->referenceNumber, fn (Builder $builder, string $value): Builder => $builder->where('reference_number', $value))
            ->when($query->actorType, fn (Builder $builder, string $value): Builder => $builder->where('actor_type', $value))
            ->when($query->actorId, fn (Builder $builder, string $value): Builder => $builder->where('actor_id', $value))
            ->when($query->subjectType, fn (Builder $builder, string $value): Builder => $builder->where('subject_type', $value))
            ->when($query->subjectId, fn (Builder $builder, string $value): Builder => $builder->where('subject_id', $value))
            ->when($query->correlationId, fn (Builder $builder, string $value): Builder => $builder->where('correlation_id', $value))
            ->when($query->causationId, fn (Builder $builder, string $value): Builder => $builder->where('causation_id', $value))
            ->when($query->executionId, fn (Builder $builder, string $value): Builder => $builder->where('execution_id', $value))
            ->when($query->eventType, fn (Builder $builder, string $value): Builder => $builder->where('event_type', $value));
    }
}
