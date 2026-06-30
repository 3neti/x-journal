<?php

namespace LBHurtado\XJournal\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LBHurtado\XJournal\Contracts\JournalIntegrityVerificationMetadataContract;
use LBHurtado\XJournal\Data\JournalIntegrityIssueData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class DefaultJournalIntegrityVerificationMetadataProvider implements JournalIntegrityVerificationMetadataContract
{
    public function collect(Collection $entries, array $issues): array
    {
        $issueCodes = collect($issues)->map(
            fn (JournalIntegrityIssueData $issue): string => $issue->code
        )->values()->unique()->values()->all();

        return [
            'verified_at' => CarbonImmutable::now()->toJSON(),
            'checked_entry_count' => $entries->count(),
            'issue_count' => count($issues),
            'issue_codes' => $issueCodes,
            'first_reference_number' => $entries->first()?->reference_number,
            'last_reference_number' => $entries->last()?->reference_number,
            'entry_ids' => $entries->pluck('id')->values()->all(),
        ];
    }
}
