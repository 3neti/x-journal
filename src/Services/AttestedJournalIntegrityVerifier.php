<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final readonly class AttestedJournalIntegrityVerifier
{
    public function __construct(
        private JournalIntegrityVerifier $verifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $base = $this->verifier->verify();
        $baseIssueReferences = collect($base->issues)
            ->pluck('referenceNumber')
            ->unique();
        $attestations = ExecutionJournalEntry::query()
            ->whereIn('event_type', [
                'journal.integrity_exception.attested',
                'account_funding.pay_code.integrity_exception_attested',
            ])
            ->orderBy('id')
            ->get()
            ->filter(
                fn (ExecutionJournalEntry $entry): bool => ! $baseIssueReferences
                    ->contains($entry->reference_number)
                    && data_get(
                        $entry->payload,
                        'original_entry_unchanged',
                    ) === true
                    && data_get($entry->payload, 'status')
                        === 'attested_legacy_exception',
            )
            ->map(function (ExecutionJournalEntry $entry): array {
                $originalReference = (string) (
                    data_get(
                        $entry->payload,
                        'original_reference_number',
                    )
                    ?? data_get(
                        $entry->references,
                        'causation_id',
                    )
                );
                $issueCodes = data_get(
                    $entry->payload,
                    'issue_codes',
                );

                if (! is_array($issueCodes)) {
                    $issueCodes = [
                        data_get($entry->payload, 'issue_code'),
                    ];
                }

                return [
                    'attestation_reference_number' => (string) $entry->reference_number,
                    'original_reference_number' => $originalReference,
                    'classification' => (string) data_get(
                        $entry->payload,
                        'classification',
                    ),
                    'issue_codes' => array_values(array_filter(
                        $issueCodes,
                        is_string(...),
                    )),
                ];
            })
            ->filter(
                fn (array $attestation): bool => $attestation['original_reference_number']
                    !== ''
                    && $attestation['issue_codes'] !== [],
            )
            ->values();
        $acceptedKeys = $attestations
            ->flatMap(
                static fn (array $attestation): array => array_map(
                    static fn (string $issueCode): string => $attestation['original_reference_number']
                        .':'.$issueCode,
                    $attestation['issue_codes'],
                ),
            )
            ->unique();
        $outstanding = collect($base->issues)
            ->reject(
                fn (object $issue): bool => $acceptedKeys->contains(
                    $issue->referenceNumber.':'.$issue->code,
                ),
            )
            ->values();
        $verified = $outstanding->isEmpty();

        return [
            'schema' => 'x-journal.operational-integrity-verification.v1',
            'verified' => $verified,
            'status' => match (true) {
                $base->verified => 'verified',
                $verified => 'verified_with_attested_legacy_exceptions',
                default => 'unverified',
            },
            'base_verified' => $base->verified,
            'checked_entry_count' => $base->checkedEntryCount,
            'base_issue_count' => count($base->issues),
            'attested_exception_count' => $attestations->count(),
            'outstanding_issue_count' => $outstanding->count(),
            'outstanding_issues' => $outstanding
                ->map(fn (object $issue): array => $issue->toArray())
                ->all(),
            'attested_exceptions' => $attestations->all(),
        ];
    }
}
