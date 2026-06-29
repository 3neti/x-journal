<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class ExecutionReferenceNumberGenerator
{
    public function generate(?CarbonInterface $occurredAt = null): string
    {
        $occurredAt ??= CarbonImmutable::now();

        $prefix = (string) config('x-journal.reference_number.prefix', 'ERN');
        $digits = (int) config('x-journal.reference_number.digits', 9);
        $year = $occurredAt->format('Y');
        $needle = "{$prefix}-{$year}-";

        $latest = ExecutionJournalEntry::query()
            ->where('reference_number', 'like', "{$needle}%")
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $next = 1;

        if (is_string($latest)) {
            $next = ((int) substr($latest, -$digits)) + 1;
        }

        return $needle.str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
    }
}
