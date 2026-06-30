<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Models\ExecutionStatementSnapshot;

class ExecutionStatementSnapshotGenerator
{
    public function __construct(
        protected ExecutionStatementSnapshotHasher $hasher,
    ) {}

    /**
     * @param  iterable<int, ExecutionJournalEntry>  $entries
     */
    public function generate(
        string $statementType,
        string $subjectType,
        string $subjectId,
        iterable $entries,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        array $openingJson = [],
        array $activityJson = [],
        array $closingJson = [],
        array $payloadJson = [],
        ?string $generatedByType = null,
        ?string $generatedById = null,
        ?string $statementNumber = null,
        ?CarbonInterface $generatedAt = null,
    ): ExecutionStatementSnapshot {
        $entries = $entries instanceof Collection ? $entries : collect($entries);
        $generatedAt ??= CarbonImmutable::now();
        $statementNumber ??= $this->nextStatementNumber($generatedAt);

        $entriesHash = $this->hasher->entriesHash($entries);

        $payload = [
            'statement_number' => $statementNumber,
            'statement_type' => $statementType,
            'period_start' => $periodStart->toJSON(),
            'period_end' => $periodEnd->toJSON(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'opening_json' => $openingJson,
            'activity_json' => $activityJson,
            'closing_json' => $closingJson,
            'entries_count' => $entries->count(),
            'entries_hash' => $entriesHash,
            'generated_at' => $generatedAt->toJSON(),
            'generated_by_type' => $generatedByType,
            'generated_by_id' => $generatedById,
            'payload_json' => $payloadJson,
            'previous_hash' => ExecutionStatementSnapshot::query()->latest('id')->value('hash'),
        ];

        $payload['hash'] = $this->hasher->fingerprint($payload);

        return ExecutionStatementSnapshot::query()->create($payload);
    }

    protected function nextStatementNumber(CarbonInterface $generatedAt): string
    {
        $year = $generatedAt->format('Y');
        $next = ExecutionStatementSnapshot::query()
            ->whereYear('generated_at', $year)
            ->count() + 1;

        return sprintf('STM-%s-%09d', $year, $next);
    }
}
