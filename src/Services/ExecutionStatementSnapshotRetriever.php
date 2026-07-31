<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotQueryData;
use LBHurtado\XJournal\Data\ExecutionStatementSnapshotResultData;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;

class ExecutionStatementSnapshotRetriever
{
    public function __construct(
        protected ExecutionStatementSnapshotHasher $hasher,
    ) {}

    public function findByStatementNumber(string $statementNumber): ?ExecutionStatementSnapshot
    {
        return ExecutionStatementSnapshot::query()
            ->where('statement_number', $statementNumber)
            ->first();
    }

    public function latestForSubject(string $statementType, string $subjectType, string $subjectId): ?ExecutionStatementSnapshot
    {
        return ExecutionStatementSnapshot::query()
            ->where('statement_type', $statementType)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('generated_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function search(ExecutionStatementSnapshotQueryData $query): ExecutionStatementSnapshotResultData
    {
        $builder = $this->applyFilters(ExecutionStatementSnapshot::query(), $query);
        $total = (clone $builder)->count();

        $snapshots = $builder
            ->orderBy('generated_at', $query->order)
            ->orderBy('id', $query->order)
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ExecutionStatementSnapshotResultData(
            snapshots: $snapshots,
            total: $total,
            limit: $query->limit,
            offset: $query->offset,
        );
    }

    public function verifyChainForQuery(ExecutionStatementSnapshotQueryData $query): bool
    {
        $snapshots = $this->orderedQueryForVerification($query)->get();

        return $this->hasher->snapshotChainIsValid($snapshots->all());
    }

    public function allMatchingQuery(ExecutionStatementSnapshotQueryData $query): Builder
    {
        return $this->applyFilters(ExecutionStatementSnapshot::query(), $query);
    }

    public function orderedQueryForVerification(ExecutionStatementSnapshotQueryData $query): Builder
    {
        return $this->allMatchingQuery($query)
            ->orderBy('generated_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * @param  Builder<ExecutionStatementSnapshot>  $builder
     * @return Builder<ExecutionStatementSnapshot>
     */
    protected function applyFilters(Builder $builder, ExecutionStatementSnapshotQueryData $query): Builder
    {
        return $builder
            ->when($query->statementType, fn (Builder $builder, string $value): Builder => $builder->where('statement_type', $value))
            ->when($query->subjectType, fn (Builder $builder, string $value): Builder => $builder->where('subject_type', $value))
            ->when($query->subjectId, fn (Builder $builder, string $value): Builder => $builder->where('subject_id', $value))
            ->when($query->statementNumber, fn (Builder $builder, string $value): Builder => $builder->where('statement_number', $value))
            ->when($query->generatedAfter, fn (Builder $builder, CarbonInterface $value): Builder => $builder->where('generated_at', '>=', $value))
            ->when($query->generatedBefore, fn (Builder $builder, CarbonInterface $value): Builder => $builder->where('generated_at', '<=', $value));
    }
}
