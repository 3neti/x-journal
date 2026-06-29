<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LBHurtado\XJournal\Models\ExecutionJournalReferenceCounter;

class ExecutionReferenceNumberGenerator
{
    public function generate(?CarbonInterface $occurredAt = null): string
    {
        $occurredAt ??= CarbonImmutable::now();

        $prefix = (string) config('x-journal.reference_number.prefix', 'ERN');
        $digits = (int) config('x-journal.reference_number.digits', 9);
        $year = $occurredAt->format('Y');
        return DB::transaction(function () use ($prefix, $year, $digits): string {
            $counter = ExecutionJournalReferenceCounter::query()
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $counter instanceof ExecutionJournalReferenceCounter) {
                $counter = ExecutionJournalReferenceCounter::query()->create([
                    'prefix' => $prefix,
                    'year' => $year,
                    'next_sequence' => 1,
                ]);
            }

            $sequence = $counter->next_sequence;

            $counter->forceFill([
                'next_sequence' => $sequence + 1,
            ])->save();

            return "{$prefix}-{$year}-".str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
        });
    }
}
