<?php

namespace LBHurtado\XJournal\Services;

use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XJournal\Data\JournalRetrievalQueryData;
use LBHurtado\XJournal\Data\JournalRetrievalResultData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class JournalEntryRetriever
{
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
